<?php

namespace App\Http\Controllers;

use App\Models\Postulante;
use App\Models\Postulacion;
use App\Models\PostulanteMerito;
use App\Models\MeritoArchivo;
use App\Models\Oferta;
use App\Models\Convocatoria;
use App\Models\TipoDocumento;
use Illuminate\Http\Request;
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

        // Group by Sede
        $grouped = $ofertas->groupBy('sede_id')->map(function ($sedeOfertas, $sedeId) {
            $sede = $sedeOfertas->first()->sede;

            return [
                'id' => $sede->id,
                'nombre' => $sede->nombre,
                'departamento' => $sede->departamento,
                'cargos' => $sedeOfertas->map(function ($oferta) {
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
                })->values()
            ];
        })->values();

        return response()->json($grouped);
    }

    /**
     * Get requirements (Tipos de Documento) for a specific convocatoria
     */
    public function requisitosConvocatoria($convocatoriaId)
    {
        $convocatoria = Convocatoria::findOrFail($convocatoriaId);

        $requisitosIds = $convocatoria->config_requisitos_ids ?? [];

        if (empty($requisitosIds)) {
            return response()->json([]);
        }

        $requisitos = TipoDocumento::whereIn('id', $requisitosIds)
            ->orderBy('orden')
            ->get();

        return response()->json($requisitos);
    }

    /**
     * Process the complete application (postulación)
     * Supports multiple offers (cargos) in a single submission
     */
    public function postular(Request $request)
    {
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

                // Files
                'foto_perfil' => 'nullable|image|max:2048',
                'ci_archivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:2048',
                'cv_pdf' => 'nullable|file|mimes:pdf|max:2048',
                'carta_postulacion' => 'nullable|file|mimes:pdf|max:2048',

                // Dynamic merits
                'meritos' => 'nullable|array',
                'meritos.*.tipo_documento_id' => 'required|exists:tipos_documento,id',
                'meritos.*.respuestas' => 'nullable|array',
                'meritos.*.archivos' => 'nullable|array',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación en el formulario.',
                'errors' => $e->errors()
            ], 422);
        }

        return DB::transaction(function () use ($validated, $request) {
            try {
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

                // 2. Create or update Postulante
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

                // 3. Handle file uploads
                if ($request->hasFile('foto_perfil')) {
                    $path = $request->file('foto_perfil')->store('postulantes/fotos', 'public');
                    $postulante->foto_perfil_path = $path;
                }

                if ($request->hasFile('ci_archivo')) {
                    $path = $request->file('ci_archivo')->store('postulantes/ci', 'public');
                    $postulante->ci_archivo_path = $path;
                }

                if ($request->hasFile('cv_pdf')) {
                    $path = $request->file('cv_pdf')->store('postulantes/cv', 'public');
                    $postulante->cv_pdf_path = $path;
                }

                if ($request->hasFile('carta_postulacion')) {
                    $path = $request->file('carta_postulacion')->store('postulantes/cartas', 'public');
                    $postulante->carta_postulacion_path = $path;
                }

                $postulante->save();

                // 4. Create ONE Postulacion per each oferta
                $postulacionIds = [];
                $ofertasData = $request->input('ofertas_detalle', []);

                foreach ($validated['oferta_ids'] as $ofertaId) {
                    // Evitar duplicados de postulación activa para el mismo postulante y oferta
                    $existe = Postulacion::where('postulante_id', $postulante->id)
                        ->where('oferta_id', $ofertaId)
                        ->whereIn('estado', ['enviada', 'en_revision', 'validada'])
                        ->exists();

                    if ($existe) {
                        $cargo = Oferta::find($ofertaId)->cargo->nombre;
                        throw new \Exception("Usted ya tiene una postulación registrada y vigente para el cargo de: {$cargo}.");
                    }

                    $detalle = collect($ofertasData)->firstWhere('oferta_id', $ofertaId);

                    $postulacion = Postulacion::create([
                        'postulante_id' => $postulante->id,
                        'oferta_id' => $ofertaId,
                        'pretension_salarial' => $detalle['pretension_salarial'] ?? $validated['pretension_salarial'],
                        'porque_cargo' => $detalle['porque_cargo'] ?? $validated['porque_cargo'],
                        'estado' => 'enviada',
                        'fecha_postulacion' => now(),
                    ]);
                    $postulacionIds[] = $postulacion->id;
                }

                // 5. Process Meritos
                $meritos = $validated['meritos'] ?? [];
                foreach ($meritos as $index => $meritoData) {
                    $merito = PostulanteMerito::create([
                        'postulante_id' => $postulante->id,
                        'tipo_documento_id' => $meritoData['tipo_documento_id'],
                        'respuestas' => $meritoData['respuestas'] ?? [],
                        'estado_verificacion' => 'pendiente',
                    ]);

                    if ($request->hasFile("meritos.{$index}.archivos")) {
                        $archivos = $request->file("meritos.{$index}.archivos");
                        foreach ($archivos as $configId => $archivo) {
                            $path = $archivo->store("postulantes/meritos/{$postulante->id}", 'public');
                            MeritoArchivo::create([
                                'merito_id' => $merito->id,
                                'config_archivo_id' => $configId,
                                'archivo_path' => $path,
                            ]);
                        }
                    }
                }

                $codigoBase = $validated['ci'];

                return response()->json([
                    'success' => true,
                    'message' => 'Postulación registrada exitosamente.',
                    'data' => [
                        'postulante_id' => $postulante->id,
                        'postulacion_ids' => $postulacionIds,
                        'cantidad_cargos' => count($postulacionIds),
                        'codigo_seguimiento' => $codigoBase,
                    ]
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
            } catch (\Throwable $te) {
                DB::rollBack();
                \Log::error("Error crítico en postulación: " . $te->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Error interno crítico al procesar la solicitud. Por favor contacte a soporte.'
                ], 500);
            }
        });
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
}
