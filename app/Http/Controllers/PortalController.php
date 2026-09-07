<?php

namespace App\Http\Controllers;

use App\Models\Postulante;
use App\Models\Postulacion;
use App\Models\PostulanteMerito;
use App\Models\MeritoArchivo;
use App\Models\Oferta;
use App\Models\Convocatoria;
use App\Models\TipoDocumento;
use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PortalController extends Controller
{
    /**
     * Get active offers grouped by Sede
     * Returns Sedes that have at least one active convocatoria
     */
    public function ofertasActivas()
    {
        $hoy = now()->toDateString();

        // Get all active offers with their relationships
        $ofertas = Oferta::with(['sede', 'cargo', 'convocatoria'])
            ->whereHas('convocatoria', function ($q) use ($hoy) {
                $q->where('fecha_inicio', '<=', $hoy)
                  ->where('fecha_cierre', '>=', $hoy);
            })
            ->get();

        // Group by Sede and filter out those without a valid Sede object
        $grouped = $ofertas->groupBy('sede_id')->map(function ($sedeOfertas, $sedeId) {
            $firstOferta = $sedeOfertas->first();
            $sede = $firstOferta->sede;

            if (!$sede) {
                return null;
            }

            return [
                'id' => $sede->id,
                'nombre' => $sede->nombre,
                'departamento' => $this->normalizeDepartamento($sede->departamento),
                'cargos' => $sedeOfertas->map(function ($oferta) {
                    if (!$oferta->cargo) {
                        return null;
                    }
                    return [
                        'oferta_id' => $oferta->id,
                        'convocatoria_id' => $oferta->convocatoria_id,
                        'cargo_id' => $oferta->cargo_id,
                        'cargo_nombre' => $oferta->cargo->nombre,
                        'vacantes' => $oferta->vacantes,
                        'convocatoria' => [
                            'id' => $oferta->convocatoria->id,
                            'titulo' => $oferta->convocatoria->titulo,
                            'fecha_cierre' => $oferta->convocatoria->fecha_cierre,
                        ]
                    ];
                })->filter()->values()
            ];
        })->filter()->values();

        return response()->json($grouped);
    }

    /**
     * Normalizes department names to match the frontend map GeoJSON
     */
    private function normalizeDepartamento($dept)
    {
        $dept = trim(mb_strtoupper($dept));
        $map = [
            'SANTA CRUZ' => 'Santa Cruz',
            'COCHABAMBA' => 'Cochabamba',
            'PANDO'      => 'Pando',
            'BENI'       => 'Beni',
            'LA PAZ'     => 'La Paz',
            'ORURO'      => 'Oruro',
            'POTOSI'     => 'Potosí',
            'POTOSÍ'     => 'Potosí',
            'CHUQUISACA' => 'Chuquisaca',
            'TARIJA'     => 'Tarija',
        ];

        return $map[$dept] ?? mb_convert_case($dept, MB_CASE_TITLE, "UTF-8");
    }

    /**
     * Get requirements (Tipos de Documento) for a specific convocatoria
     */
    public function requisitosConvocatoria($convocatoriaId)
    {
        $convocatoria = Convocatoria::findOrFail($convocatoriaId);

        $requisitosIds = $convocatoria->config_requisitos_ids ?? [];
        $opcionales = $convocatoria->requisitos_opcionales ?? [];

        if (empty($requisitosIds)) {
            return response()->json([]);
        }

        $schemas = collect(\App\Support\MeritSchemaRegistry::all());
        $requisitos = $schemas->whereIn('id', $requisitosIds)
            ->map(function($req) use ($opcionales) {
                // Ensure array format is converted to object or formatted properly
                $req['opcional'] = in_array($req['id'], $opcionales);
                return $req;
            })
            ->values();

        return response()->json($requisitos);
    }

    /**
     * Process the complete application (postulación)
     * Supports multiple offers (cargos) in a single submission
     */
    public function postular(Request $request)
    {
        $startTotal = microtime(true);
        \Log::info('=== POSTULAR START ===', [
            'ip' => $request->ip(),
            'content_length' => $request->header('Content-Length'),
            'files_count' => count($request->allFiles()),
        ]);
        try {
            $validated = $request->validate([
                // Offer selection - NOW ACCEPTS ARRAY
                'oferta_ids' => 'required|array|min:1',
                'oferta_ids.*' => 'exists:ofertas,id',

                // Personal data
                'ci' => 'required|string|max:20',
                'ci_expedido' => 'required|string|max:10',
                'nombres' => 'required|string|max:255',
                'apellidos' => 'required|string|max:255',
                'nacionalidad' => 'required|string|max:50',
                'direccion_domicilio' => 'required|string|max:500',
                'email' => 'required|email|max:255',
                'celular' => 'required|string|max:20',

                // References
                'ref_personal_celular' => 'required|string|max:20',
                'ref_personal_parentesco' => 'required|string|max:255',
                'ref_laboral_celular' => 'required|string|max:20',
                'ref_laboral_detalle' => 'required|string|max:500',
                'pretension_salarial' => 'nullable|numeric|min:0',
                'porque_cargo' => 'nullable|string|max:1000',

                // Per-Offer details
                'ofertas_detalle' => 'nullable|array',
                'ofertas_detalle.*.oferta_id' => 'required|exists:ofertas,id',
                'ofertas_detalle.*.pretension_salarial' => 'required|numeric|min:0',
                'ofertas_detalle.*.porque_cargo' => 'required|string|max:1000',
                'has_archivos' => 'nullable|boolean',
                'archivo_tokens' => 'nullable|array',
                'archivo_tokens.foto_perfil' => 'nullable|string',
                'archivo_tokens.ci_archivo' => 'nullable|string',
                'archivo_tokens.cv_pdf' => 'nullable|string',
                'archivo_tokens.carta_postulacion' => 'nullable|string',

                // Dynamic merits
                'meritos' => 'nullable|array',
                'meritos.*.tipo_documento_id' => 'required|exists:tipos_documento,id',
                'meritos.*.respuestas' => 'nullable|array',
                'meritos.*.archivo_tokens' => 'nullable|array',
                'meritos.*.archivo_tokens.*' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación en el formulario.',
                'errors' => $e->errors()
            ], 422);
        }

        \Log::info('POSTULAR: Validación OK', ['elapsed' => round((microtime(true) - $startTotal) * 1000) . 'ms']);

        $dbData = DB::transaction(function () use ($validated, $request, $startTotal) {
            $hoy = now()->toDateString();

            // 1. Verificación de que las convocatorias siguen abiertas
            $ofertasInvalidas = Oferta::whereIn('id', $validated['oferta_ids'])
                ->whereHas('convocatoria', function ($q) use ($hoy) {
                    $q->where('fecha_inicio', '>', $hoy)
                      ->orWhere('fecha_cierre', '<', $hoy);
                })->with('cargo')->get();

            if ($ofertasInvalidas->count() > 0) {
                $nombres = $ofertasInvalidas->map(fn($o) => $o->cargo->nombre)->join(', ');
                throw new \Exception("La(s) convocatoria(s) para: [{$nombres}] ya no se encuentran vigentes o han cerrado.");
            }

            \Log::info('POSTULAR: Convocatorias verificadas', ['elapsed' => round((microtime(true) - $startTotal) * 1000) . 'ms']);

            // 2. Create or update Postulante local (DB only, no files yet)
            $postulante = Postulante::updateOrCreate(
                ['ci' => $validated['ci']],
                [
                    'ci_expedido' => $validated['ci_expedido'] ?? null,
                    'nombres' => $validated['nombres'],
                    'apellidos' => $validated['apellidos'],
                    'nacionalidad' => $request->input('nacionalidad', 'Boliviana'),
                    'direccion_domicilio' => $request->input('direccion_domicilio'),
                    'email' => $request->input('email'),
                    'celular' => $request->input('celular'),
                    'ref_personal_celular' => $request->input('ref_personal_celular'),
                    'ref_personal_parentesco' => $request->input('ref_personal_parentesco'),
                    'ref_laboral_celular' => $request->input('ref_laboral_celular'),
                    'ref_laboral_detalle' => $request->input('ref_laboral_detalle'),
                    'pretension_salarial' => $validated['pretension_salarial'] ?? null,
                    'porque_cargo' => $validated['porque_cargo'] ?? null,
                ]
            );

            \Log::info('POSTULAR: Postulante DB OK', ['elapsed' => round((microtime(true) - $startTotal) * 1000) . 'ms']);

            // 3. Create ONE Postulacion per each oferta (DB only)
            $postulacionIds = [];
            $ofertasData = $request->input('ofertas_detalle', []);

            foreach ($validated['oferta_ids'] as $ofertaId) {
                $existe = Postulacion::where('postulante_id', $postulante->id)
                    ->where('oferta_id', $ofertaId)
                    ->whereIn('estado', ['pendiente_archivos', 'enviada', 'en_revision', 'validada'])
                    ->exists();

                if ($existe) {
                    $cargo = Oferta::find($ofertaId)->cargo->nombre;
                    throw new \Exception("Usted ya tiene una postulación registrada y vigente para el cargo de: {$cargo}.");
                }

                $detalleRaw = collect($ofertasData)->firstWhere('oferta_id', $ofertaId);
                $detalle = is_array($detalleRaw) ? $detalleRaw : [];

                $postulacion = Postulacion::create([
                    'postulante_id' => $postulante->id,
                    'oferta_id' => $ofertaId,
                    'pretension_salarial' => $detalle['pretension_salarial'] ?? $validated['pretension_salarial'],
                    'porque_cargo' => $detalle['porque_cargo'] ?? $validated['porque_cargo'],
                    'estado' => 'pendiente_archivos',
                    'fecha_postulacion' => now(),
                ]);
                $postulacionIds[] = $postulacion->id;
            }

            \Log::info('POSTULAR: Postulaciones DB OK', ['elapsed' => round((microtime(true) - $startTotal) * 1000) . 'ms']);

            // 4. Process Meritos (DB only)
            $meritosCreated = [];
            $meritos = $validated['meritos'] ?? [];
            foreach ($meritos as $index => $meritoData) {
                $merito = PostulanteMerito::create([
                    'postulante_id' => $postulante->id,
                    'tipo_documento_id' => $meritoData['tipo_documento_id'],
                    'respuestas' => $meritoData['respuestas'] ?? [],
                    'estado_verificacion' => 'pendiente',
                ]);
                $meritosCreated[] = ['id' => $merito->id, 'index' => $index];
            }

            \Log::info('POSTULAR: Meritos DB OK', ['elapsed' => round((microtime(true) - $startTotal) * 1000) . 'ms']);

            return [
                'postulante' => $postulante,
                'postulacionIds' => $postulacionIds,
                'meritosCreated' => $meritosCreated
            ];
        });

        // ---------------------------------------------------------
        // Mover archivos temporales a destino final (protegido con try-catch)
        // Si falla, la postulación YA ESTÁ guardada en la DB, NO se pierde.
        // El postulante puede reintentar la subida de archivos luego.
        // ---------------------------------------------------------
        $archivosWarning = null;
        try {
            $this->materializarArchivosTemporales($request, $dbData);
        } catch (\Throwable $e) {
            $archivosWarning = 'Sus datos de postulación fueron guardados, pero hubo un problema al procesar algunos archivos adjuntos. Por favor, contacte al administrador si sus documentos no aparecen en el sistema.';
            \Log::error('POSTULAR: Error al materializar archivos (postulacion guardada)', [
                'postulante_id' => $dbData['postulante']->id,
                'postulacion_ids' => $dbData['postulacionIds'],
                'error' => $e->getMessage(),
                'elapsed' => round((microtime(true) - $startTotal) * 1000) . 'ms',
            ]);
        }

        // ---------------------------------------------------------
        // Responder al usuario (la DB ya está guardada con o sin archivos)
        // ---------------------------------------------------------
        $postulante = $dbData['postulante'];
        $codigoBase = $postulante->ci;
        $uploadToken = Crypt::encryptString(json_encode([
            'postulante_id' => $postulante->id,
            'postulacion_ids' => $dbData['postulacionIds'],
            'exp' => now()->addMinutes(30)->timestamp,
        ]));

        \Log::info('POSTULAR: DB completa, enviando respuesta inmediata', [
            'elapsed' => round((microtime(true) - $startTotal) * 1000) . 'ms',
            'archivos_ok' => $archivosWarning === null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $archivosWarning
                ? 'Postulación registrada, pero con advertencia en archivos.'
                : 'Postulación registrada exitosamente.',
            'archivos_warning' => $archivosWarning,
            'data' => [
                'postulante_id' => $postulante->id,
                'postulacion_ids' => $dbData['postulacionIds'],
                'codigo_seguimiento' => $codigoBase,
                'upload_token' => $uploadToken,
                'meritos_upload' => $dbData['meritosCreated'],
            ]
        ], 201);

    }

    /**
     * Upload files after the postulation data has already been saved.
     * This keeps the public submit fast and avoids browser network timeouts.
     */
    public function subirArchivosPostulacion(Request $request)
    {
        $validated = $request->validate([
            'postulante_id' => 'required|integer|exists:postulantes,id',
            'upload_token' => 'required|string',
            'foto_perfil' => 'nullable|image|max:5120',
            'ci_archivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
            'cv_pdf' => 'nullable|file|mimes:pdf|max:5120',
            'carta_postulacion' => 'nullable|file|mimes:pdf|max:5120',
            'meritos' => 'nullable|array',
            'meritos.*.merito_id' => 'required_with:meritos|integer|exists:postulante_meritos,id',
            'meritos.*.archivos' => 'nullable|array',
            'meritos.*.archivos.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
        ]);

        try {
            $payload = json_decode(Crypt::decryptString($validated['upload_token']), true);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token de subida invalido.',
            ], 403);
        }

        if (
            !is_array($payload)
            || (int) ($payload['postulante_id'] ?? 0) !== (int) $validated['postulante_id']
            || (int) ($payload['exp'] ?? 0) < now()->timestamp
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Token de subida vencido o no corresponde al postulante.',
            ], 403);
        }

        $postulante = Postulante::findOrFail($validated['postulante_id']);

        DB::transaction(function () use ($request, $postulante, $payload) {
            if ($request->hasFile('foto_perfil')) {
                $postulante->foto_perfil_path = $request->file('foto_perfil')->store('postulantes/fotos', 'public');
            }
            if ($request->hasFile('ci_archivo')) {
                $postulante->ci_archivo_path = $request->file('ci_archivo')->store('postulantes/ci', 'public');
            }
            if ($request->hasFile('cv_pdf')) {
                $postulante->cv_pdf_path = $request->file('cv_pdf')->store('postulantes/cv', 'public');
            }
            if ($request->hasFile('carta_postulacion')) {
                $postulante->carta_postulacion_path = $request->file('carta_postulacion')->store('postulantes/cartas', 'public');
            }
            $postulante->save();

            foreach ($request->input('meritos', []) as $index => $meritoData) {
                $merito = PostulanteMerito::where('id', $meritoData['merito_id'] ?? null)
                    ->where('postulante_id', $postulante->id)
                    ->first();

                if (!$merito || !$request->hasFile("meritos.{$index}.archivos")) {
                    continue;
                }

                foreach ($request->file("meritos.{$index}.archivos") as $configId => $archivo) {
                    $path = $archivo->store("postulantes/meritos/{$postulante->id}", 'public');
                    MeritoArchivo::updateOrCreate(
                        [
                            'merito_id' => $merito->id,
                            'config_archivo_id' => $configId,
                        ],
                        ['archivo_path' => $path]
                    );
                }
            }

            $postulacionIds = array_filter(array_map('intval', $payload['postulacion_ids'] ?? []));
            if (!empty($postulacionIds)) {
                Postulacion::whereIn('id', $postulacionIds)
                    ->where('postulante_id', $postulante->id)
                    ->where('estado', 'pendiente_archivos')
                    ->update(['estado' => 'enviada']);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Archivos de postulacion cargados correctamente.',
        ]);
    }

    public function subirArchivoTemporal(Request $request)
    {
        $validated = $request->validate([
            'scope' => 'required|string|in:personal,merito',
            'field' => 'required|string|max:80',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
        ]);

        $start = microtime(true);
        $file = $request->file('file');
        $path = $file->store('postulaciones/tmp', 'public');

        $token = Crypt::encryptString(json_encode([
            'path' => $path,
            'scope' => $validated['scope'],
            'field' => $validated['field'],
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'exp' => now()->addHours(6)->timestamp,
        ]));

        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        \Log::info('POSTULAR TEMP FILE OK', [
            'scope' => $validated['scope'],
            'field' => $validated['field'],
            'size' => $file->getSize(),
            'elapsed' => $elapsedMs . 'ms',
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'server_elapsed_ms' => $elapsedMs,
            'file' => [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ],
        ])->header('X-SISPO-Store-Time-Ms', (string) $elapsedMs);
    }

    private function materializarArchivosTemporales(Request $request, array $dbData): void
    {
        $postulante = $dbData['postulante'];
        $start = microtime(true);
        $archivosMaterializados = []; // Track files moved, to clean up on partial failure

        try {
            DB::transaction(function () use ($request, $postulante, $dbData, &$archivosMaterializados) {
                $personalTokens = $request->input('archivo_tokens', []);
                $personalTargets = [
                    'foto_perfil'        => ['column' => 'foto_perfil_path',       'dir' => 'postulantes/fotos'],
                    'ci_archivo'         => ['column' => 'ci_archivo_path',        'dir' => 'postulantes/ci'],
                    'cv_pdf'             => ['column' => 'cv_pdf_path',            'dir' => 'postulantes/cv'],
                    'carta_postulacion'  => ['column' => 'carta_postulacion_path', 'dir' => 'postulantes/cartas'],
                ];

                foreach ($personalTargets as $field => $target) {
                    if (empty($personalTokens[$field])) continue;

                    $path = $this->moverArchivoTemporal($personalTokens[$field], $target['dir']);
                    $archivosMaterializados[] = $path;
                    $postulante->{$target['column']} = $path;
                }
                $postulante->save();

                $meritosInput = $request->input('meritos', []);
                foreach ($dbData['meritosCreated'] as $mInfo) {
                    $index = $mInfo['index'];
                    $tokens = $meritosInput[$index]['archivo_tokens'] ?? [];

                    foreach ($tokens as $configId => $token) {
                        if (!$token) continue;

                        $path = $this->moverArchivoTemporal($token, "postulantes/meritos/{$postulante->id}");
                        $archivosMaterializados[] = $path;
                        MeritoArchivo::updateOrCreate(
                            ['merito_id' => $mInfo['id'], 'config_archivo_id' => $configId],
                            ['archivo_path' => $path]
                        );
                    }
                }

                Postulacion::whereIn('id', $dbData['postulacionIds'])
                    ->where('postulante_id', $postulante->id)
                    ->where('estado', 'pendiente_archivos')
                    ->update(['estado' => 'enviada']);
            });
        } catch (\Throwable $e) {
            // Limpiar archivos que ya fueron movidos antes del fallo (evitar archivos huérfanos)
            foreach ($archivosMaterializados as $path) {
                try {
                    Storage::disk('public')->delete($path);
                } catch (\Throwable $cleanupEx) {
                    \Log::warning('POSTULAR: No se pudo limpiar archivo tras fallo de materialización', [
                        'path' => $path,
                        'error' => $cleanupEx->getMessage(),
                    ]);
                }
            }
            throw $e; // Re-throw para que el caller (postular) lo atrape con su try-catch
        }

        \Log::info('POSTULAR: Archivos temporales materializados', [
            'postulante_id' => $postulante->id,
            'total_archivos' => count($archivosMaterializados),
            'elapsed' => round((microtime(true) - $start) * 1000) . 'ms',
        ]);
    }

    private function moverArchivoTemporal(string $token, string $targetDir): string
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable $e) {
            throw new \Exception('Token de archivo invalido.');
        }

        if (!is_array($payload) || (int) ($payload['exp'] ?? 0) < now()->timestamp) {
            throw new \Exception('Token de archivo vencido.');
        }

        $source = $payload['path'] ?? '';
        if (!$source || !Storage::disk('public')->exists($source)) {
            throw new \Exception('Archivo temporal no encontrado.');
        }

        $extension = pathinfo($source, PATHINFO_EXTENSION);
        $filename = uniqid('', true) . ($extension ? ".{$extension}" : '');
        $target = trim($targetDir, '/') . '/' . $filename;

        Storage::disk('public')->makeDirectory($targetDir);
        Storage::disk('public')->move($source, $target);

        return $target;
    }

    /**
     * Get all active sedes for selection
     */
    public function sedes()
    {
        return response()->json(Sede::where('activo', true)->orderBy('nombre')->get());
    }

    /**
     * Check application status by CI
     */
    public function consultar($ci)
    {
        $postulante = Postulante::where('ci', $ci)
            ->with(['postulaciones.oferta.cargo', 'postulaciones.oferta.sede'])
            ->first();

        if (!$postulante) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró ninguna postulación con el CI proporcionado.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'postulante' => [
                'nombres' => $postulante->nombres,
                'apellidos' => $postulante->apellidos,
            ],
            'postulaciones' => $postulante->postulaciones->map(function ($p) {
                return [
                    'id' => $p->id,
                    'cargo' => $p->oferta->cargo->nombre,
                    'sede' => $p->oferta->sede->nombre,
                    'estado' => $p->estado,
                    'fecha' => $p->fecha_postulacion,
                    'pretension_salarial' => $p->pretension_salarial,
                    'porque_cargo' => $p->porque_cargo,
                ];
            })
        ]);
    }

    /**
     * Verify if a postulante exists and check against email for "Double Key" security
     */
    public function verificarPostulante(Request $request)
    {
        $request->validate([
            'ci' => 'required|string',
            'email' => 'nullable|email'
        ]);

        $postulante = Postulante::where('ci', $request->ci)
            ->with(['meritos.archivos'])
            ->first();

        if (!$postulante) {
            return response()->json([
                'exists' => false,
                'message' => 'Cédula no registrada. Puede proceder con un nuevo registro.'
            ]);
        }

        // If email is provided, check if it matches to grant "edit access" without login
        if ($request->filled('email')) {
            if (strtolower(trim($postulante->email)) === strtolower(trim($request->email))) {
                $postulante->load([
                    'formacionesAcademicas',
                    'formacionesPostgrado',
                    'experienciasDocencia',
                    'experienciasProfesionales',
                    'capacitaciones',
                    'produccionesIntelectuales',
                    'reconocimientos',
                    'sede'
                ]);
                $formatted = (new \App\Http\Resources\ExpedienteNormalizedResource($postulante))->toArray(request());

                return response()->json([
                    'exists' => true,
                    'verified' => true,
                    'data' => $formatted,
                    'message' => 'Identidad verificada. Cargando sus datos actuales...'
                ]);
            } else {
                return response()->json([
                    'exists' => true,
                    'verified' => false,
                    'message' => 'El correo electrónico no coincide con nuestro registro para esta Cédula.'
                ], 403);
            }
        }

        return response()->json([
            'exists' => true,
            'verified' => false,
            'message' => 'Este CI ya está registrado. Ingrese su correo electrónico para editar sus datos.'
        ]);
    }

    /**
     * Get all document types for the "Direct Registration" form
     */
    public function tiposDocumentoGenerales()
    {
        return response()->json(\App\Support\MeritSchemaRegistry::all());
    }

    /**
     * Direct registration/update without requiring a full postulación or user login
     */
    public function registrarDirecto(Request $request)
    {
        try {
            $validated = $request->validate([
                'ci' => 'required|string|max:20',
                'ci_expedido' => 'nullable|string|max:10',
                'nombres' => 'required|string|max:255',
                'apellidos' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'email_institucional' => 'nullable|email|max:255',
                'sede_id' => 'nullable|exists:core.sedes,id_sede',
                'celular' => 'nullable|string|max:20',
                'nacionalidad' => 'nullable|string|max:50',
                'direccion_domicilio' => 'nullable|string|max:500',
                'clasificacion' => 'nullable|string|max:50',
                'ref_personal_celular' => 'nullable|string|max:20',
                'ref_personal_parentesco' => 'nullable|string|max:255',

                // Files
                'foto_perfil' => 'nullable|image|max:2048',
                'ci_archivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                'cv_pdf' => 'nullable|file|mimes:pdf|max:2048',

                // Merits
                'meritos' => 'nullable|array',
            ]);

            return DB::transaction(function () use ($validated, $request) {
                // 1. Update or Create Postulante local
                $postulante = Postulante::updateOrCreate(
                    ['ci' => $validated['ci']],
                    [
                        'ci_expedido' => $validated['ci_expedido'] ?? null,
                        'sede_id' => $validated['sede_id'] ?? null,
                        'nombres' => $validated['nombres'],
                        'apellidos' => $validated['apellidos'],
                        'email' => $validated['email'],
                        'email_institucional' => $validated['email_institucional'] ?? null,
                        'celular' => $validated['celular'] ?? null,
                        'nacionalidad' => $validated['nacionalidad'] ?? 'Boliviana',
                        'direccion_domicilio' => $validated['direccion_domicilio'] ?? null,
                        'clasificacion' => $validated['clasificacion'] ?? 'ADMINISTRATIVO',
                        'ref_personal_celular' => $validated['ref_personal_celular'] ?? null,
                        'ref_personal_parentesco' => $validated['ref_personal_parentesco'] ?? null,
                    ]
                );

                // 2. Handle Files
                if ($request->hasFile('foto_perfil')) {
                    $postulante->foto_perfil_path = $request->file('foto_perfil')->store('postulantes/fotos', 'public');
                }
                if ($request->hasFile('ci_archivo')) {
                    $postulante->ci_archivo_path = $request->file('ci_archivo')->store('postulantes/ci', 'public');
                }
                if ($request->hasFile('cv_pdf')) {
                    $postulante->cv_pdf_path = $request->file('cv_pdf')->store('postulantes/cv', 'public');
                }
                $postulante->save();

                // 3. Process Meritos (NATIVE NORMALIZED - FASE 4)
                $meritosData = $request->input('meritos', []);
                foreach ($meritosData as $index => $mData) {
                    $tipoId = (int)$mData['tipo_documento_id'];
                    $respuestas = is_string($mData['respuestas']) ? json_decode($mData['respuestas'], true) : ($mData['respuestas'] ?? []);

                    $cleanId = null;
                    if (!empty($mData['id'])) {
                        $cleanId = (int) preg_replace('/[^0-9]/', '', $mData['id']);
                    }

                    $modelClass = match ($tipoId) {
                        1 => \App\Models\FormacionAcademica::class,
                        2 => \App\Models\FormacionPostgrado::class,
                        3 => \App\Models\ExperienciaDocencia::class,
                        4 => \App\Models\ExperienciaProfesional::class,
                        5 => \App\Models\Capacitacion::class,
                        6 => \App\Models\ProduccionIntelectual::class,
                        7 => \App\Models\Reconocimiento::class,
                        default => null,
                    };

                    if (!$modelClass) {
                        continue;
                    }

                    $row = null;
                    if ($cleanId) {
                        $row = $modelClass::where('id', $cleanId)->where('postulante_id', $postulante->id)->first();
                    }
                    if (!$row) {
                        $row = new $modelClass();
                        $row->postulante_id = $postulante->id;
                    }

                    if ($tipoId === 1) {
                        $row->nivel_academico_raw = $respuestas['nivel'] ?? null;
                        $row->universidad = $respuestas['universidad'] ?? null;
                        $row->carrera_raw = $respuestas['profesion'] ?? ($respuestas['carrera'] ?? null);
                        $row->fecha_diploma = $respuestas['fecha_diploma'] ?? null;
                        $row->fecha_titulo = $respuestas['fecha_titulo'] ?? null;
                    } elseif ($tipoId === 2) {
                        $row->tipo_posgrado_raw = $respuestas['tipo_posgrado'] ?? null;
                        $row->nombre_programa = $respuestas['nombre_programa'] ?? null;
                        $row->fecha_certificacion = $respuestas['fecha_certificacion'] ?? ($respuestas['fecha'] ?? null);
                        $row->institucion = $respuestas['institucion'] ?? null;
                    } elseif ($tipoId === 3) {
                        $row->universidad = $respuestas['universidad'] ?? null;
                        $row->carrera_raw = $respuestas['carrera'] ?? null;
                        $row->asignaturas = $respuestas['asignaturas'] ?? null;
                        $row->gestion_periodo = $respuestas['gestion_periodo'] ?? null;
                    } elseif ($tipoId === 4) {
                        $row->cargo_raw = $respuestas['cargo'] ?? null;
                        $row->empresa = $respuestas['empresa'] ?? null;
                        $row->fecha_inicio = $respuestas['fecha_inicio'] ?? null;
                        $row->fecha_fin = $respuestas['fecha_fin'] ?? null;
                        
                        $months = 0;
                        if (!empty($respuestas['fecha_inicio']) && !empty($respuestas['fecha_fin'])) {
                            try {
                                $start = new \DateTime($respuestas['fecha_inicio']);
                                $end = new \DateTime($respuestas['fecha_fin']);
                                $diff = $start->diff($end);
                                $months = ($diff->y * 12) + $diff->m;
                            } catch (\Exception $ex) {}
                        }
                        $row->duracion_meses = $months;
                    } elseif ($tipoId === 5) {
                        $row->nombre_curso = $respuestas['nombre'] ?? null;
                        $row->fecha = $respuestas['fecha'] ?? null;
                        $row->institucion_organizadora = $respuestas['institucion'] ?? null;
                        $row->carga_horaria = (int)($respuestas['horas'] ?? 0);
                    } elseif ($tipoId === 6) {
                        $row->tipo_produccion_raw = $respuestas['tipo'] ?? null;
                        $row->titulo = $respuestas['titulo'] ?? null;
                        $row->fecha_publicacion = $respuestas['fecha'] ?? null;
                        $row->editorial_revista = $respuestas['editorial'] ?? null;
                        $row->lugar = $respuestas['lugar'] ?? null;
                    } elseif ($tipoId === 7) {
                        $row->titulo_reconocimiento = $respuestas['titulo'] ?? null;
                        $row->fecha = $respuestas['fecha'] ?? null;
                        $row->institucion_otorgante = $respuestas['institucion'] ?? null;
                        $row->lugar = $respuestas['lugar'] ?? null;
                    }

                    // Handle files
                    $files = $request->file("meritos.{$index}.archivos");
                    if ($files && is_array($files)) {
                        foreach ($files as $configKey => $file) {
                             if ($file instanceof \Illuminate\Http\UploadedFile) {
                                 $path = $file->store("postulantes/{$postulante->id}/meritos", 'public');

                                 if ($tipoId === 1) {
                                     if ($configKey === 'diploma') {
                                         $row->diploma_archivo_path = $path;
                                         $row->diploma_archivo_original_name = $file->getClientOriginalName();
                                         $row->diploma_archivo_mime = $file->getClientMimeType();
                                     } elseif ($configKey === 'titulo') {
                                         $row->titulo_archivo_path = $path;
                                         $row->titulo_archivo_original_name = $file->getClientOriginalName();
                                         $row->titulo_archivo_mime = $file->getClientMimeType();
                                     }
                                 } else {
                                     $prefix = match ($tipoId) {
                                         2 => 'certificado_archivo',
                                         3 => 'respaldo_archivo',
                                         4 => 'certificado_archivo',
                                         5 => 'certificado_archivo',
                                         6 => 'evidencia_archivo',
                                         7 => 'reconocimiento_archivo',
                                         default => null,
                                     };
                                     if ($prefix) {
                                         $pathCol = "{$prefix}_path";
                                         $nameCol = "{$prefix}_original_name";
                                         $mimeCol = "{$prefix}_mime";
                                         
                                         $row->$pathCol = $path;
                                         $row->$nameCol = $file->getClientOriginalName();
                                         $row->$mimeCol = $file->getClientMimeType();
                                     }
                                 }
                             }
                        }
                    }

                    $row->save();
                }

                // 4. Link user if it exists (staff registration)
                if (!$postulante->user_id) {
                    try {
                        $user = null;
                        if (Schema::connection('core')->hasTable('users')) {
                            // Try Has Persona relation
                            try {
                                $user = \App\Models\User::whereHas('persona', function ($query) use ($postulante) {
                                    $query->where('ci', $postulante->ci);
                                })->first();
                            } catch (\Throwable $eInner) {}

                            // Fallback to direct 'ci' lookup if column exists
                            if (!$user && Schema::connection('core')->hasColumn('users', 'ci')) {
                                $user = \App\Models\User::where('ci', $postulante->ci)->first();
                            }
                        }

                        if ($user) {
                            $postulante->user_id = $user->id;
                            $postulante->save();
                        }
                    } catch (\Throwable $e) {
                        \Log::warning("No se pudo asociar usuario al postulante: " . $e->getMessage());
                    }
                }

                $postulante->load([
                    'formacionesAcademicas',
                    'formacionesPostgrado',
                    'experienciasDocencia',
                    'experienciasProfesionales',
                    'capacitaciones',
                    'produccionesIntelectuales',
                    'reconocimientos',
                    'sede'
                ]);

                $formatted = (new \App\Http\Resources\ExpedienteNormalizedResource($postulante))->toArray(request());

                return response()->json([
                    'success' => true,
                    'message' => 'Información registrada correctamente.',
                    'postulante' => $formatted
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 400);
        }
    }
}
