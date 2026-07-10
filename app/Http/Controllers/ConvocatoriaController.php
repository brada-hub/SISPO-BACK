<?php

namespace App\Http\Controllers;

use App\Models\Convocatoria;
use App\Models\Oferta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConvocatoriaController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $query = Convocatoria::query();
        $allowedConvocatorias = $this->allowedConvocatoriaIds($user);
        $allowedSedes = $this->allowedSedeIds($user);

        if ($this->shouldLimitByConvocatoria($user)) {
            $query->whereIn('id', $allowedConvocatorias);
        } elseif (!empty($allowedSedes)) {
            $query->whereHas('ofertas', function ($q) use ($allowedSedes) {
                $q->whereIn('sede_id', $allowedSedes);
            });
        }

        return $query->with(['ofertas' => function ($q) use ($user, $allowedSedes) {
            if (!$this->shouldLimitByConvocatoria($user) && !empty($allowedSedes)) {
                $q->whereIn('sede_id', $allowedSedes);
            }
            $q->with(['sede', 'cargo']);
        }])->get();
    }

    /**
     * Get active/open convocatorias for the public portal
     */
    public function abiertas()
    {
        $hoy = now()->toDateString();

        return Convocatoria::with(['ofertas.sede', 'ofertas.cargo'])
            ->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_cierre', '>=', $hoy)
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'codigo_interno' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string',
            'contenido_detalle' => 'nullable|string',
            'fecha_inicio' => 'required|date',
            'fecha_cierre' => 'required|date|after_or_equal:fecha_inicio',
            'hora_limite' => 'nullable',
            'config_requisitos_ids' => 'nullable|array',
            'requisitos_opcionales' => 'nullable|array',
            'requisitos_afiche' => 'nullable|array',
            'matriz_evaluacion' => 'nullable|array',
            'ofertas' => 'required|array|min:1',
            'ofertas.*.sede_id' => 'required|exists:sedes,id',
            'ofertas.*.cargo_id' => 'required|exists:cargos,id',
            'estado' => 'nullable|string|in:draft,reviewing,published,archived,closed',
            'score_profile_id' => 'nullable|integer',
        ]);

        $estado = $validated['estado'] ?? 'draft';

        // Strict business validation if publishing immediately (FASE 4)
        if ($estado === 'published') {
            $this->performStrictBusinessValidation($validated);
        }

        return DB::transaction(function () use ($validated, $estado) {
            $convocatoria = Convocatoria::create([
                'titulo' => $validated['titulo'],
                'codigo_interno' => $validated['codigo_interno'],
                'descripcion' => $validated['descripcion'],
                'contenido_detalle' => $validated['contenido_detalle'] ?? null,
                'fecha_inicio' => $validated['fecha_inicio'],
                'fecha_cierre' => $validated['fecha_cierre'],
                'hora_limite' => $validated['hora_limite'],
                'config_requisitos_ids' => $validated['config_requisitos_ids'] ?? [],
                'requisitos_opcionales' => $validated['requisitos_opcionales'] ?? [],
                'requisitos_afiche' => $validated['requisitos_afiche'] ?? [],
                'matriz_evaluacion' => $validated['matriz_evaluacion'] ?? null,
                'estado' => $estado,
                'score_profile_id' => $validated['score_profile_id'] ?? null,
                'draft_versions' => [],
            ]);

            foreach ($validated['ofertas'] as $o) {
                Oferta::create([
                    'convocatoria_id' => $convocatoria->id,
                    'sede_id' => $o['sede_id'],
                    'cargo_id' => $o['cargo_id'],
                    'vacantes' => $o['vacantes'] ?? 1,
                ]);
            }

            // Log Audit (FASE 3)
            $this->logAudit($convocatoria->id, 'create', [
                'titulo' => $convocatoria->titulo,
                'estado' => $convocatoria->estado
            ]);

            return $convocatoria->load(['ofertas.sede', 'ofertas.cargo']);
        });
    }

    public function show($id)
    {
        $user = auth()->user();
        $query = Convocatoria::query();
        $allowedConvocatorias = $this->allowedConvocatoriaIds($user);
        $allowedSedes = $this->allowedSedeIds($user);

        if ($this->shouldLimitByConvocatoria($user)) {
            $query->whereIn('id', $allowedConvocatorias);
        } elseif (!empty($allowedSedes)) {
            $query->whereHas('ofertas', function ($q) use ($allowedSedes) {
                $q->whereIn('sede_id', $allowedSedes);
            });
        }

        $convocatoria = $query->findOrFail($id);

        return $convocatoria->load(['ofertas' => function ($q) use ($user, $allowedSedes) {
            if (!$this->shouldLimitByConvocatoria($user) && !empty($allowedSedes)) {
                $q->whereIn('sede_id', $allowedSedes);
            }
            $q->with(['sede', 'cargo']);
        }]);
    }

    public function update(Request $request, Convocatoria $convocatoria)
    {
        try {
            $validated = $request->validate([
                'titulo' => 'required|string|max:255',
                'codigo_interno' => 'nullable|string|max:50',
                'descripcion' => 'nullable|string',
                'contenido_detalle' => 'nullable|string',
                'fecha_inicio' => 'required|date',
                'fecha_cierre' => 'required|date|after_or_equal:fecha_inicio',
                'hora_limite' => 'nullable',
                'config_requisitos_ids' => 'nullable|array',
                'requisitos_opcionales' => 'nullable|array',
                'requisitos_afiche' => 'nullable|array',
                'matriz_evaluacion' => 'nullable|array',
                'ofertas' => 'required|array|min:1',
                'ofertas.*.sede_id' => 'required|exists:sedes,id',
                'ofertas.*.cargo_id' => 'required|exists:cargos,id',
                'estado' => 'nullable|string|in:draft,reviewing,published,archived,closed',
                'score_profile_id' => 'nullable|integer',
                'restore_version_index' => 'nullable|integer',
            ]);

            $estado = $validated['estado'] ?? $convocatoria->estado ?? 'draft';

            // Strict business validation if publishing (FASE 4)
            if ($estado === 'published') {
                $this->performStrictBusinessValidation($validated);
            }

            return DB::transaction(function () use ($validated, $convocatoria, $estado, $request) {
                // Draft Versioning Engine (FASE 2)
                $versions = $convocatoria->draft_versions ?? [];
                
                // If restore requested
                if (isset($validated['restore_version_index']) && isset($versions[$validated['restore_version_index']])) {
                    $restoredState = $versions[$validated['restore_version_index']]['form_state'];
                    $validated['titulo'] = $restoredState['titulo'] ?? $validated['titulo'];
                    $validated['descripcion'] = $restoredState['descripcion'] ?? $validated['descripcion'];
                    $validated['config_requisitos_ids'] = $restoredState['config_requisitos_ids'] ?? $validated['config_requisitos_ids'];
                    $validated['matriz_evaluacion'] = $restoredState['matriz_evaluacion'] ?? $validated['matriz_evaluacion'];
                    $this->logAudit($convocatoria->id, 'restore_version', ['index' => $validated['restore_version_index']]);
                } else {
                    // Auto-generate version if it is draft or reviewing
                    if ($estado === 'draft' || $estado === 'reviewing') {
                        if (count($versions) >= 10) {
                            array_shift($versions);
                        }
                        $versions[] = [
                            'timestamp' => now()->toIso8601String(),
                            'user_id' => auth()->id(),
                            'user_name' => auth()->user()?->name ?? 'Sistema',
                            'form_state' => [
                                'titulo' => $convocatoria->titulo,
                                'descripcion' => $convocatoria->descripcion,
                                'config_requisitos_ids' => $convocatoria->config_requisitos_ids,
                                'matriz_evaluacion' => $convocatoria->matriz_evaluacion,
                            ]
                        ];
                    }
                }

                $changesList = [];
                if ($convocatoria->titulo !== $validated['titulo']) $changesList['titulo'] = $validated['titulo'];
                if ($convocatoria->estado !== $estado) $changesList['estado'] = $estado;
                if (json_encode($convocatoria->matriz_evaluacion) !== json_encode($validated['matriz_evaluacion'])) $changesList['matriz_evaluacion'] = 'Updated evaluation matrix';
                if (json_encode($convocatoria->config_requisitos_ids) !== json_encode($validated['config_requisitos_ids'])) $changesList['config_requisitos_ids'] = 'Updated requirements';

                $convocatoria->update([
                    'titulo' => $validated['titulo'],
                    'codigo_interno' => $validated['codigo_interno'],
                    'descripcion' => $validated['descripcion'],
                    'contenido_detalle' => $validated['contenido_detalle'] ?? null,
                    'fecha_inicio' => $validated['fecha_inicio'],
                    'fecha_cierre' => $validated['fecha_cierre'],
                    'hora_limite' => $validated['hora_limite'],
                    'config_requisitos_ids' => $validated['config_requisitos_ids'] ?? [],
                    'requisitos_opcionales' => $validated['requisitos_opcionales'] ?? [],
                    'requisitos_afiche' => $validated['requisitos_afiche'] ?? [],
                    'matriz_evaluacion' => $validated['matriz_evaluacion'] ?? null,
                    'estado' => $estado,
                    'score_profile_id' => $validated['score_profile_id'] ?? null,
                    'draft_versions' => $versions,
                ]);

                // Sync Ofertas correctly to prevent CASCADE DELETE of postulaciones
                $existingOfertas = $convocatoria->ofertas()->get();
                $keptOfertaIds = [];

                foreach ($validated['ofertas'] as $o) {
                    $oferta = $existingOfertas->firstWhere(function ($val) use ($o) {
                        return $val->sede_id == $o['sede_id'] && $val->cargo_id == $o['cargo_id'];
                    });

                    if ($oferta) {
                        $oferta->update(['vacantes' => $o['vacantes'] ?? 1]);
                        $keptOfertaIds[] = $oferta->id;
                    } else {
                        $newOferta = Oferta::create([
                            'convocatoria_id' => $convocatoria->id,
                            'sede_id' => $o['sede_id'],
                            'cargo_id' => $o['cargo_id'],
                            'vacantes' => $o['vacantes'] ?? 1,
                        ]);
                        $keptOfertaIds[] = $newOferta->id;
                    }
                }

                $convocatoria->ofertas()->whereNotIn('id', $keptOfertaIds)->delete();

                // Log Audit (FASE 3)
                if (!empty($changesList)) {
                    $this->logAudit($convocatoria->id, 'update', $changesList);
                }

                return $convocatoria->load(['ofertas.sede', 'ofertas.cargo']);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación de negocio',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            \Log::error("Error actualizando convocatoria {$convocatoria->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno al actualizar la convocatoria: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Convocatoria $convocatoria)
    {
        $id = $convocatoria->id;
        $titulo = $convocatoria->titulo;
        
        DB::transaction(function () use ($convocatoria, $id, $titulo) {
            $convocatoria->delete();
            $this->logAudit($id, 'delete', ['titulo' => $titulo]);
        });
        
        return response()->noContent();
    }

    /**
     * Public endpoint to get convocatoria details for the landing page
     */
    public function showPublic($id)
    {
        $convocatoria = Convocatoria::with(['ofertas.sede', 'ofertas.cargo'])
            ->findOrFail($id);

        return response()->json($convocatoria);
    }

    public function convocatoriasConPostulantes()
    {
        $user = auth()->user();
        $allowedConvocatorias = $this->allowedConvocatoriaIds($user);
        $allowedSedes = $this->allowedSedeIds($user);

        $query = Convocatoria::withCount(['postulaciones' => function ($q) use ($user, $allowedConvocatorias, $allowedSedes) {
            if ($this->shouldLimitByConvocatoria($user)) {
                $q->whereHas('oferta', function ($oq) use ($allowedConvocatorias) {
                    $oq->whereIn('convocatoria_id', $allowedConvocatorias);
                });
            } elseif (!empty($allowedSedes)) {
                $q->whereHas('oferta', function ($oq) use ($allowedSedes) {
                    $oq->whereIn('sede_id', $allowedSedes);
                });
            }
        }]);

        if ($this->shouldLimitByConvocatoria($user)) {
            $query->whereIn('id', $allowedConvocatorias);
        } elseif (!empty($allowedSedes)) {
            $query->whereHas('ofertas', function ($q) use ($allowedSedes) {
                $q->whereIn('sede_id', $allowedSedes);
            });
        }

        return $query->with(['ofertas' => function ($q) use ($user, $allowedSedes) {
            if (!$this->shouldLimitByConvocatoria($user) && !empty($allowedSedes)) {
                $q->whereIn('sede_id', $allowedSedes);
            }
            $q->with('sede');
        }])->orderBy('fecha_inicio', 'desc')->get();
    }

    private function performStrictBusinessValidation(array $validated)
    {
        // 1. Matriz == 100
        $matriz = $validated['matriz_evaluacion'] ?? [];
        $totalPts = 0;
        foreach ($matriz as $sec) {
            foreach ($sec['criterios'] ?? [] as $c) {
                $totalPts += intval($c['puntaje'] ?? 0);
            }
        }
        if ($totalPts !== 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'matriz_evaluacion' => ["La matriz de evaluación debe sumar exactamente 100 puntos (actual: $totalPts pts) para poder publicar."]
            ]);
        }

        // 2. Fechas coherentes
        $inicio = $validated['fecha_inicio'] ?? null;
        $cierre = $validated['fecha_cierre'] ?? null;
        if ($inicio && $cierre && strtotime($cierre) < strtotime($inicio)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fecha_cierre' => ["La fecha de cierre no puede ser anterior a la fecha de inicio."]
            ]);
        }

        // 3. Cargos/Sedes obligatorios
        $ofertas = $validated['ofertas'] ?? [];
        if (count($ofertas) === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ofertas' => ["Debe asociar al menos una sede y un cargo para publicar la convocatoria."]
            ]);
        }

        // 4. Requisitos mínimos
        $reqs = $validated['config_requisitos_ids'] ?? [];
        if (count($reqs) === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'config_requisitos_ids' => ["Debe seleccionar al menos un requisito mínimo para el perfil."]
            ]);
        }
    }

    private function logAudit($convocatoriaId, $action, $changes = null)
    {
        DB::table('convocatoria_audit_logs')->insert([
            'convocatoria_id' => $convocatoriaId,
            'user_id' => auth()->id(),
            'action' => $action,
            'changes' => is_array($changes) ? json_encode($changes) : $changes,
            'created_at' => now(),
        ]);
    }

    private function shouldLimitByConvocatoria($user): bool
    {
        return $user && !$user->isAdminUser() && $user->hasConvocatoriaScope();
    }

    private function allowedConvocatoriaIds($user): array
    {
        return $user ? $user->allowedConvocatoriaIds() : [];
    }

    private function allowedSedeIds($user): array
    {
        if (!$user || $user->isAdminUser() || $user->hasConvocatoriaScope()) {
            return [];
        }

        return $user->allowedSedeIds();
    }
}
