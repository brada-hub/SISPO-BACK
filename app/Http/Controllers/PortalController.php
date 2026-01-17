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
        $validated = $request->validate([
            // Offer selection - NOW ACCEPTS ARRAY
            'oferta_ids' => 'required|array|min:1',
            'oferta_ids.*' => 'exists:ofertas,id',

            // Personal data
            'ci' => 'required|string|max:20',
            'ci_expedido' => 'nullable|string|max:10',
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'nacionalidad' => 'nullable|string|max:50',
            'direccion_domicilio' => 'nullable|string|max:500',
            'email' => 'nullable|email|max:255',
            'celular' => 'nullable|string|max:20',

            // References
            'ref_personal_celular' => 'nullable|string|max:20',
            'ref_personal_parentesco' => 'nullable|string|max:255',
            'ref_laboral_celular' => 'nullable|string|max:20',
            'ref_laboral_detalle' => 'nullable|string|max:500',

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

        return DB::transaction(function () use ($validated, $request) {
            // 1. Create or update Postulante
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
                ]
            );

            // 2. Handle file uploads
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

            // 3. Create ONE Postulacion per each oferta_id
            $postulacionIds = [];
            foreach ($validated['oferta_ids'] as $ofertaId) {
                $postulacion = Postulacion::create([
                    'postulante_id' => $postulante->id,
                    'oferta_id' => $ofertaId,
                    'estado' => 'enviada',
                    'fecha_postulacion' => now(),
                ]);
                $postulacionIds[] = $postulacion->id;
            }

            // 4. Process Meritos (dynamic document requirements) - linked to postulante
            $meritos = $validated['meritos'] ?? [];

            foreach ($meritos as $index => $meritoData) {
                $merito = PostulanteMerito::create([
                    'postulante_id' => $postulante->id,
                    'tipo_documento_id' => $meritoData['tipo_documento_id'],
                    'respuestas' => $meritoData['respuestas'] ?? [],
                    'estado_verificacion' => 'pendiente',
                ]);

                // Handle merit files if any
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

            // Generate a single tracking code for all applications (now using CI)
            $codigoBase = $validated['ci'];

            return response()->json([
                'success' => true,
                'message' => 'Postulación registrada exitosamente para ' . count($postulacionIds) . ' cargo(s)',
                'data' => [
                    'postulante_id' => $postulante->id,
                    'postulacion_ids' => $postulacionIds,
                    'cantidad_cargos' => count($postulacionIds),
                    'codigo_seguimiento' => $codigoBase,
                ]
            ], 201);
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
                ];
            })
        ]);
    }
}
