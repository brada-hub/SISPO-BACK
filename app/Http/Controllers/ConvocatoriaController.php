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
        ]);

        return DB::transaction(function () use ($validated) {
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
            ]);

            foreach ($validated['ofertas'] as $o) {
                Oferta::create([
                    'convocatoria_id' => $convocatoria->id,
                    'sede_id' => $o['sede_id'],
                    'cargo_id' => $o['cargo_id'],
                    'vacantes' => $o['vacantes'] ?? 1,
                ]);
            }

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
            ]);

            return DB::transaction(function () use ($validated, $convocatoria) {
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
                ]);

                // Sync Ofertas correctly to prevent CASCADE DELETE of postulaciones
                $existingOfertas = $convocatoria->ofertas()->get();
                $keptOfertaIds = [];

                foreach ($validated['ofertas'] as $o) {
                    $oferta = $existingOfertas->firstWhere(function ($val) use ($o) {
                        return $val->sede_id == $o['sede_id'] && $val->cargo_id == $o['cargo_id'];
                    });

                    if ($oferta) {
                        // Update existing to avoid changing its ID
                        $oferta->update(['vacantes' => $o['vacantes'] ?? 1]);
                        $keptOfertaIds[] = $oferta->id;
                    } else {
                        // Create new
                        $newOferta = Oferta::create([
                            'convocatoria_id' => $convocatoria->id,
                            'sede_id' => $o['sede_id'],
                            'cargo_id' => $o['cargo_id'],
                            'vacantes' => $o['vacantes'] ?? 1,
                        ]);
                        $keptOfertaIds[] = $newOferta->id;
                    }
                }

                // Delete only ofertas that were explicitly removed from the configuration
                $convocatoria->ofertas()->whereNotIn('id', $keptOfertaIds)->delete();

                return $convocatoria->load(['ofertas.sede', 'ofertas.cargo']);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
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
        $convocatoria->delete();
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
