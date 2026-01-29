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
        return Convocatoria::with(['ofertas.sede', 'ofertas.cargo'])->get();
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
            'fecha_inicio' => 'required|date',
            'fecha_cierre' => 'required|date|after_or_equal:fecha_inicio',
            'hora_limite' => 'nullable',
            'config_requisitos_ids' => 'nullable|array',
            'requisitos_opcionales' => 'nullable|array',
            'requisitos_afiche' => 'nullable|array',
            'ofertas' => 'required|array|min:1',
            'ofertas.*.sede_id' => 'required|exists:sedes,id',
            'ofertas.*.cargo_id' => 'required|exists:cargos,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $convocatoria = Convocatoria::create([
                'titulo' => $validated['titulo'],
                'codigo_interno' => $validated['codigo_interno'],
                'descripcion' => $validated['descripcion'],
                'fecha_inicio' => $validated['fecha_inicio'],
                'fecha_cierre' => $validated['fecha_cierre'],
                'hora_limite' => $validated['hora_limite'],
                'config_requisitos_ids' => $validated['config_requisitos_ids'] ?? [],
                'requisitos_opcionales' => $validated['requisitos_opcionales'] ?? [],
                'requisitos_afiche' => $validated['requisitos_afiche'] ?? [],
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

    public function show(Convocatoria $convocatoria)
    {
        return $convocatoria->load(['ofertas.sede', 'ofertas.cargo']);
    }

    public function update(Request $request, Convocatoria $convocatoria)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'codigo_interno' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string',
            'fecha_inicio' => 'required|date',
            'fecha_cierre' => 'required|date|after_or_equal:fecha_inicio',
            'hora_limite' => 'nullable',
            'config_requisitos_ids' => 'nullable|array',
            'requisitos_opcionales' => 'nullable|array',
            'requisitos_afiche' => 'nullable|array',
            'ofertas' => 'required|array|min:1',
            'ofertas.*.sede_id' => 'required|exists:sedes,id',
            'ofertas.*.cargo_id' => 'required|exists:cargos,id',
        ]);

        return DB::transaction(function () use ($validated, $convocatoria) {
            $convocatoria->update([
                'titulo' => $validated['titulo'],
                'codigo_interno' => $validated['codigo_interno'],
                'descripcion' => $validated['descripcion'],
                'fecha_inicio' => $validated['fecha_inicio'],
                'fecha_cierre' => $validated['fecha_cierre'],
                'hora_limite' => $validated['hora_limite'],
                'config_requisitos_ids' => $validated['config_requisitos_ids'] ?? [],
                'requisitos_opcionales' => $validated['requisitos_opcionales'] ?? [],
                'requisitos_afiche' => $validated['requisitos_afiche'] ?? [],
            ]);

            // Sync Ofertas (delete all and recreating is simplest for this scope)
            $convocatoria->ofertas()->delete();
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

    public function destroy(Convocatoria $convocatoria)
    {
        $convocatoria->delete();
        return response()->noContent();
    }

    public function convocatoriasConPostulantes()
    {
        $user = auth()->user();
        $query = Convocatoria::withCount(['postulaciones' => function($q) use ($user) {
            if ($user && $user->rol->nombre !== 'ADMINISTRADOR' && $user->sede_id) {
                $q->whereHas('oferta', function($oq) use ($user) {
                    $oq->where('sede_id', $user->sede_id);
                });
            }
        }]);

        // Si el usuario es limitado, solo mostrar convocatorias que tienen ofertas en su sede
        if ($user && $user->rol->nombre !== 'ADMINISTRADOR' && $user->sede_id) {
            $query->whereHas('ofertas', function($q) use ($user) {
                $q->where('sede_id', $user->sede_id);
            });
        }

        return $query->with(['ofertas' => function($q) use ($user) {
            if ($user && $user->rol->nombre !== 'ADMINISTRADOR' && $user->sede_id) {
                $q->where('sede_id', $user->sede_id);
            }
            $q->with('sede');
        }])->orderBy('fecha_inicio', 'desc')->get();
    }
}
