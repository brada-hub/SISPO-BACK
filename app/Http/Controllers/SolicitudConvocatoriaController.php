<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\SolicitudConvocatoria;
use App\Models\Convocatoria;
use App\Models\Oferta;
use App\Models\Sede;
use App\Models\Cargo;
use App\Models\TipoDocumento;

class SolicitudConvocatoriaController extends Controller
{
    /**
     * Catálogos públicos de Sedes y Cargos para el formulario de solicitud.
     */
    public function catalogosPublicos()
    {
        $sedes = Sede::orderBy('nombre')->get(['id_sede', 'nombre', 'sigla', 'departamento']);
        $cargos = Cargo::orderBy('nombre')->get(['id', 'nombre']);

        return response()->json([
            'sedes' => $sedes->map(fn($s) => [
                'id' => $s->id_sede,
                'nombre' => $s->nombre,
                'sigla' => $s->sigla,
                'departamento' => $s->departamento,
            ]),
            'cargos' => $cargos,
        ]);
    }

    /**
     * Consulta pública del estado de recepción (Activo / Desactivado).
     */
    public function getRecepcionStatus()
    {
        $habRow = DB::table('sispo_configs')->where('key', 'recepcion_solicitudes_habilitada')->first();
        $msgRow = DB::table('sispo_configs')->where('key', 'recepcion_solicitudes_mensaje_cierre')->first();

        return response()->json([
            'habilitada' => $habRow ? (bool) intval($habRow->value) : true,
            'mensaje_cierre' => $msgRow ? $msgRow->value : 'El periodo de recepción de requerimientos se encuentra actualmente cerrado.',
        ]);
    }

    /**
     * Switch administrativo para activar/desactivar la recepción de solicitudes.
     */
    public function toggleRecepcionStatus(Request $request)
    {
        $validated = $request->validate([
            'habilitada' => 'required|boolean',
            'mensaje_cierre' => 'nullable|string|max:500',
        ]);

        DB::table('sispo_configs')->updateOrInsert(
            ['key' => 'recepcion_solicitudes_habilitada'],
            [
                'value' => $validated['habilitada'] ? '1' : '0',
                'updated_by_user_id' => auth()->id(),
                'updated_at' => now(),
            ]
        );

        if (isset($validated['mensaje_cierre'])) {
            DB::table('sispo_configs')->updateOrInsert(
                ['key' => 'recepcion_solicitudes_mensaje_cierre'],
                [
                    'value' => $validated['mensaje_cierre'],
                    'updated_by_user_id' => auth()->id(),
                    'updated_at' => now(),
                ]
            );
        }

        return response()->json([
            'message' => 'Estado de recepción de solicitudes actualizado exitosamente.',
            'habilitada' => (bool) $validated['habilitada'],
            'mensaje_cierre' => $validated['mensaje_cierre'] ?? null,
        ]);
    }

    /**
     * Registro público de solicitud por parte del Director o Jefe de Carrera.
     */
    public function storePublic(Request $request)
    {
        // Verificar si la recepción está habilitada
        $habRow = DB::table('sispo_configs')->where('key', 'recepcion_solicitudes_habilitada')->first();
        if ($habRow && intval($habRow->value) === 0) {
            $msgRow = DB::table('sispo_configs')->where('key', 'recepcion_solicitudes_mensaje_cierre')->first();
            return response()->json([
                'message' => $msgRow ? $msgRow->value : 'El periodo de recepción de requerimientos de convocatoria se encuentra actualmente cerrado.'
            ], 403);
        }

        $validated = $request->validate([
            'solicitante_nombre' => 'required|string|max:150',
            'solicitante_cargo' => 'required|string|max:100',
            'solicitante_carrera' => 'required|string|max:150',
            'solicitante_email' => 'required|email|max:100',
            'solicitante_telefono' => 'required|string|max:50',
            'tipo_perfil' => 'required|string|in:docente,adm,tecnico,invest',
            'titulo_sugerido' => 'required|string|max:255',
            'descripcion_motivo' => 'nullable|string',
            'sedes_ids' => 'required|array|min:1',
            'sedes_ids.*' => 'integer',
            'cargos_ids' => 'required|array|min:1',
            'cargos_ids.*' => 'integer',
            'requisito_formacion' => 'nullable|string',
            'requisito_posgrado' => 'nullable|string',
            'requisito_experiencia_profesional' => 'nullable|string',
            'requisito_experiencia_docente' => 'nullable|string',
            'otros_requisitos' => 'nullable|string',
            'archivo_adjunto' => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
        ]);

        // Generar Código Único SOL-YYYY-XXX
        $year = date('Y');
        $count = SolicitudConvocatoria::whereYear('created_at', $year)->count() + 1;
        $codigo = sprintf('SOL-%s-%03d', $year, $count);

        $archivoPath = null;
        if ($request->hasFile('archivo_adjunto')) {
            $archivoPath = $request->file('archivo_adjunto')->store('solicitudes_respaldos', 'public');
        }

        $solicitud = SolicitudConvocatoria::create([
            'codigo_solicitud' => $codigo,
            'solicitante_nombre' => $validated['solicitante_nombre'],
            'solicitante_cargo' => $validated['solicitante_cargo'],
            'solicitante_carrera' => $validated['solicitante_carrera'],
            'solicitante_email' => $validated['solicitante_email'],
            'solicitante_telefono' => $validated['solicitante_telefono'],
            'tipo_perfil' => $validated['tipo_perfil'],
            'titulo_sugerido' => mb_strtoupper($validated['titulo_sugerido']),
            'descripcion_motivo' => $validated['descripcion_motivo'] ?? null,
            'sedes_ids' => $validated['sedes_ids'],
            'cargos_ids' => $validated['cargos_ids'],
            'requisito_formacion' => $validated['requisito_formacion'] ?? null,
            'requisito_posgrado' => $validated['requisito_posgrado'] ?? null,
            'requisito_experiencia_profesional' => $validated['requisito_experiencia_profesional'] ?? null,
            'requisito_experiencia_docente' => $validated['requisito_experiencia_docente'] ?? null,
            'otros_requisitos' => $validated['otros_requisitos'] ?? null,
            'archivo_adjunto_path' => $archivoPath,
            'estado' => 'pendiente',
        ]);

        return response()->json([
            'message' => 'Solicitud de convocatoria enviada con éxito.',
            'codigo_solicitud' => $codigo,
            'solicitud' => $solicitud,
        ], 201);
    }

    /**
     * Consulta pública del estado de una solicitud por código (SOL-2026-XXX).
     */
    public function consultarPublic($codigo)
    {
        $solicitud = SolicitudConvocatoria::where('codigo_solicitud', trim($codigo))->first();

        if (!$solicitud) {
            return response()->json(['message' => 'No se encontró ninguna solicitud con ese código.'], 404);
        }

        $sedes = Sede::whereIn('id_sede', $solicitud->sedes_ids ?? [])->get(['id_sede', 'nombre', 'sigla']);
        $cargos = Cargo::whereIn('id', $solicitud->cargos_ids ?? [])->get(['id', 'nombre']);

        return response()->json([
            'solicitud' => $solicitud,
            'sedes' => $sedes,
            'cargos' => $cargos,
        ]);
    }

    /**
     * Listado administrativo para Talento Humano.
     */
    public function index(Request $request)
    {
        $query = SolicitudConvocatoria::with(['convocatoria:id,titulo,codigo_interno,estado', 'aprobadoPor:id,name']);

        if ($request->filled('estado') && $request->estado !== 'todas') {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('codigo_solicitud', 'like', $search)
                  ->orWhere('solicitante_carrera', 'like', $search)
                  ->orWhere('solicitante_nombre', 'like', $search)
                  ->orWhere('titulo_sugerido', 'like', $search);
            });
        }

        $solicitudes = $query->orderBy('created_at', 'desc')->get();

        // Enriquecer con nombres de sedes y cargos
        $sedesMap = Sede::pluck('nombre', 'id_sede')->toArray();
        $cargosMap = Cargo::pluck('nombre', 'id')->toArray();

        $solicitudes->transform(function ($item) use ($sedesMap, $cargosMap) {
            $item->sedes_nombres = array_values(array_intersect_key($sedesMap, array_flip($item->sedes_ids ?? [])));
            $item->cargos_nombres = array_values(array_intersect_key($cargosMap, array_flip($item->cargos_ids ?? [])));
            return $item;
        });

        return response()->json($solicitudes);
    }

    /**
     * Detalle específico de una solicitud.
     */
    public function show($id)
    {
        $solicitud = SolicitudConvocatoria::with(['convocatoria', 'aprobadoPor:id,name'])->findOrFail($id);
        $sedes = Sede::whereIn('id_sede', $solicitud->sedes_ids ?? [])->get(['id_sede', 'nombre', 'sigla']);
        $cargos = Cargo::whereIn('id', $solicitud->cargos_ids ?? [])->get(['id', 'nombre']);

        return response()->json([
            'solicitud' => $solicitud,
            'sedes' => $sedes,
            'cargos' => $cargos,
        ]);
    }

    /**
     * Cambiar estado a observada o rechazada con retroalimentación de RRHH.
     */
    public function updateEstado(Request $request, $id)
    {
        $validated = $request->validate([
            'estado' => 'required|in:pendiente,observada,rechazada',
            'observaciones_rrhh' => 'nullable|string',
        ]);

        $solicitud = SolicitudConvocatoria::findOrFail($id);
        $solicitud->estado = $validated['estado'];
        if (isset($validated['observaciones_rrhh'])) {
            $solicitud->observaciones_rrhh = $validated['observaciones_rrhh'];
        }
        $solicitud->save();

        return response()->json([
            'message' => 'Estado de la solicitud actualizado correctamente.',
            'solicitud' => $solicitud,
        ]);
    }

    /**
     * EL VISTO BUENO: Genera la convocatoria en borrador enlazando sedes, cargos y requisitos.
     */
    public function aprobarYGenerarConvocatoria(Request $request, $id)
    {
        $solicitud = SolicitudConvocatoria::findOrFail($id);

        return DB::transaction(function () use ($solicitud, $request) {
            // Mapear requisitos a IDs de catálogo
            $catalogoRequisitos = TipoDocumento::all();
            $configRequisitosIds = [];
            $requisitosAfiche = [
                '__main_heading' => 'REQUERIMIENTO DE PERSONAL:'
            ];

            // Formación
            $reqFormacion = $catalogoRequisitos->first(fn($r) => str_contains(strtoupper($r->nombre), 'FORMACI'));
            if ($reqFormacion) {
                $configRequisitosIds[] = $reqFormacion->id;
                if (!empty($solicitud->requisito_formacion)) {
                    $requisitosAfiche[$reqFormacion->id] = "- " . $solicitud->requisito_formacion;
                }
            }

            // Posgrados
            $reqPosgrado = $catalogoRequisitos->first(fn($r) => str_contains(strtoupper($r->nombre), 'POSTGRADO') || str_contains(strtoupper($r->nombre), 'POSGRADO'));
            if ($reqPosgrado) {
                $configRequisitosIds[] = $reqPosgrado->id;
                if (!empty($solicitud->requisito_posgrado)) {
                    $requisitosAfiche[$reqPosgrado->id] = "- " . $solicitud->requisito_posgrado;
                }
            }

            // Experiencia Profesional
            $reqExpProf = $catalogoRequisitos->first(fn($r) => str_contains(strtoupper($r->nombre), 'PROFESIONAL'));
            if ($reqExpProf) {
                $configRequisitosIds[] = $reqExpProf->id;
                if (!empty($solicitud->requisito_experiencia_profesional)) {
                    $requisitosAfiche[$reqExpProf->id] = "- " . $solicitud->requisito_experiencia_profesional;
                }
            }

            // Experiencia Docente
            $reqExpDoc = $catalogoRequisitos->first(fn($r) => str_contains(strtoupper($r->nombre), 'DOCEN'));
            if ($reqExpDoc) {
                $configRequisitosIds[] = $reqExpDoc->id;
                if (!empty($solicitud->requisito_experiencia_docente)) {
                    $requisitosAfiche[$reqExpDoc->id] = "- " . $solicitud->requisito_experiencia_docente;
                }
            }

            // Matriz por defecto según tipo_perfil
            $matrizDefault = $this->getPresetMatriz($solicitud->tipo_perfil);

            // Código interno generado
            $codigoInterno = sprintf('CONV-%s-%03d', date('Y'), Convocatoria::count() + 1);

            // Crear la Convocatoria en borrador (draft)
            $convocatoria = Convocatoria::create([
                'titulo' => $solicitud->titulo_sugerido,
                'codigo_interno' => $codigoInterno,
                'descripcion' => "La Universidad Técnica Privada Cosmos invita a profesionales a postular al cargo de {$solicitud->titulo_sugerido}." . ($solicitud->descripcion_motivo ? " Justificación: {$solicitud->descripcion_motivo}" : ""),
                'fecha_inicio' => now()->format('Y-m-d'),
                'fecha_cierre' => now()->addDays(7)->format('Y-m-d'),
                'hora_limite' => '18:00',
                'config_requisitos_ids' => array_values(array_unique($configRequisitosIds)),
                'requisitos_opcionales' => [],
                'requisitos_afiche' => $requisitosAfiche,
                'matriz_evaluacion' => $matrizDefault,
                'estado' => 'draft',
            ]);

            // Generar las Ofertas asociadas (Sede x Cargo - SIN número de vacantes, default null o 1)
            $ofertasCreadas = [];
            foreach ($solicitud->sedes_ids as $sedeId) {
                foreach ($solicitud->cargos_ids as $cargoId) {
                    $ofertasCreadas[] = Oferta::create([
                        'convocatoria_id' => $convocatoria->id,
                        'sede_id' => $sedeId,
                        'cargo_id' => $cargoId,
                        'vacantes' => 1,
                    ]);
                }
            }

            // Marcar la solicitud como aprobada
            $solicitud->estado = 'aprobada';
            $solicitud->convocatoria_creada_id = $convocatoria->id;
            $solicitud->aprobado_por_user_id = auth()->id();
            $solicitud->aprobado_at = now();
            $solicitud->save();

            // Cargar relaciones para responder al front
            $convocatoria->load('ofertas');

            return response()->json([
                'message' => '¡Convocatoria creada exitosamente a partir de la solicitud!',
                'convocatoria' => $convocatoria,
                'solicitud' => $solicitud,
            ]);
        });
    }

    private function getPresetMatriz($tipoPerfil)
    {
        if ($tipoPerfil === 'adm') {
            return [
                [
                    'seccion' => 'FORMACIÓN GENERAL',
                    'criterios' => [
                        ['nombre' => 'Grado Académico y Cursos', 'puntaje' => 25, 'descripcion' => 'Licenciatura y certificaciones profesionales']
                    ]
                ],
                [
                    'seccion' => 'EXPERIENCIA PROFESIONAL',
                    'criterios' => [
                        ['nombre' => 'Ejercicio Profesional en Gestión', 'puntaje' => 45, 'descripcion' => 'Años laborando en funciones similares'],
                        ['nombre' => 'Competencias Técnicas y Sistemas', 'puntaje' => 30, 'descripcion' => 'Manejo de sistemas de información ATS']
                    ]
                ]
            ];
        }

        if ($tipoPerfil === 'tecnico') {
            return [
                [
                    'seccion' => 'FORMACIÓN TÉCNICA',
                    'criterios' => [
                        ['nombre' => 'Grado Técnico o Especialidad', 'puntaje' => 30, 'descripcion' => 'Titulado en áreas técnicas afines']
                    ]
                ],
                [
                    'seccion' => 'EXPERIENCIA TÉCNICA APLICADA',
                    'criterios' => [
                        ['nombre' => 'Ejercicio Profesional de Soporte', 'puntaje' => 50, 'descripcion' => 'Años laborando en laboratorios o soporte'],
                        ['nombre' => 'Cursos de Actualización Cortos', 'puntaje' => 20, 'descripcion' => 'Cursos específicos del área técnica']
                    ]
                ]
            ];
        }

        if ($tipoPerfil === 'invest') {
            return [
                [
                    'seccion' => 'GRADO CIENTÍFICO',
                    'criterios' => [
                        ['nombre' => 'Doctorado o Maestría', 'puntaje' => 30, 'descripcion' => 'Grado de Doctor (PhD) o Magíster']
                    ]
                ],
                [
                    'seccion' => 'PRODUCCIÓN CIENTÍFICA',
                    'criterios' => [
                        ['nombre' => 'Publicaciones y Revistas Indexadas', 'puntaje' => 40, 'descripcion' => 'Artículos en revistas de alto impacto'],
                        ['nombre' => 'Experiencia en Proyectos I+D', 'puntaje' => 30, 'descripcion' => 'Proyectos financiados de investigación']
                    ]
                ]
            ];
        }

        // Default: Docente
        return [
            [
                'seccion' => 'FORMACIÓN Y POSTGRADO',
                'criterios' => [
                    ['nombre' => 'Grado Académico (Licenciatura / Esp.)', 'puntaje' => 20, 'descripcion' => 'Título en Provisión Nacional en el área'],
                    ['nombre' => 'Postgrados (Diplomado / Maestría)', 'puntaje' => 20, 'descripcion' => 'Diplomado en Educación Superior y Maestrías afines']
                ]
            ],
            [
                'seccion' => 'EXPERIENCIA PROFESIONAL Y DOCENTE',
                'criterios' => [
                    ['nombre' => 'Ejercicio de la Profesión', 'puntaje' => 25, 'descripcion' => 'Años continuos de ejercicio en la especialidad'],
                    ['nombre' => 'Docencia Universitaria', 'puntaje' => 15, 'descripcion' => 'Experiencia como catedrático de grado o postgrado']
                ]
            ],
            [
                'seccion' => 'PRODUCCIÓN Y MÉRITOS',
                'criterios' => [
                    ['nombre' => 'Producción Intelectual / Libros / Artículos', 'puntaje' => 20, 'descripcion' => 'Artículos científicos o textos guía publicados']
                ]
            ]
        ];
    }
}
