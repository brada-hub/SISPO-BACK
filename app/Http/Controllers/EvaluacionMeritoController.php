<?php

namespace App\Http\Controllers;

use App\Models\EvaluacionPostulacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EvaluacionMeritoController extends Controller
{
    public function showByPostulacion($postulacionId)
    {
        $user = auth()->user();
        $allowedConvocatorias = $this->allowedConvocatoriaIds($user);
        $allowedSedes = $this->allowedSedeIds($user);
        $query = \App\Models\Postulacion::query();

        if ($this->shouldLimitByConvocatoria($user)) {
            $query->whereHas('oferta', function ($q) use ($allowedConvocatorias) {
                $q->whereIn('convocatoria_id', $allowedConvocatorias);
            });
        } elseif (!empty($allowedSedes)) {
            $query->whereHas('oferta', function ($q) use ($allowedSedes) {
                $q->whereIn('sede_id', $allowedSedes);
            });
        }

        if (!$query->where('id', $postulacionId)->exists()) {
            return response()->json(['message' => 'No tiene acceso a este expediente'], 403);
        }

        $evaluacion = EvaluacionPostulacion::where('postulacion_id', $postulacionId)->first();

        if (!$evaluacion) {
            return response()->json(null, 200);
        }

        return response()->json($evaluacion);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'postulacion_id' => 'required|exists:postulaciones,id',
            'puntaje_formacion' => 'required|numeric',
            'puntaje_perfeccionamiento' => 'required|numeric',
            'puntaje_experiencia' => 'required|numeric',
            'puntaje_otros' => 'required|numeric',
            'puntaje_total' => 'required|numeric',
            'detalle_evaluacion' => 'required|array',
            'observaciones' => 'nullable|string',
            'pretension_salarial' => 'nullable|numeric',
        ]);

        $user = auth()->user();
        $allowedConvocatorias = $this->allowedConvocatoriaIds($user);
        $allowedSedes = $this->allowedSedeIds($user);
        $query = \App\Models\Postulacion::query();

        if ($this->shouldLimitByConvocatoria($user)) {
            $query->whereHas('oferta', function ($q) use ($allowedConvocatorias) {
                $q->whereIn('convocatoria_id', $allowedConvocatorias);
            });
        } elseif (!empty($allowedSedes)) {
            $query->whereHas('oferta', function ($q) use ($allowedSedes) {
                $q->whereIn('sede_id', $allowedSedes);
            });
        }

        $postulacion = $query->findOrFail($validated['postulacion_id']);

        $evaluacion = EvaluacionPostulacion::updateOrCreate(
            ['postulacion_id' => $validated['postulacion_id']],
            [
                'evaluador_id' => Auth::id() ?? 1,
                'puntaje_formacion' => $validated['puntaje_formacion'],
                'puntaje_perfeccionamiento' => $validated['puntaje_perfeccionamiento'],
                'puntaje_experiencia' => $validated['puntaje_experiencia'],
                'puntaje_otros' => $validated['puntaje_otros'],
                'puntaje_total' => $validated['puntaje_total'],
                'detalle_evaluacion' => $validated['detalle_evaluacion'],
                'observaciones' => $validated['observaciones'],
            ]
        );

        if ($postulacion->estado === 'enviada') {
            $postulacion->estado = 'en_revision';
        }

        if ($request->has('pretension_salarial')) {
            $postulacion->pretension_salarial = $validated['pretension_salarial'];
        }

        $postulacion->save();

        return response()->json([
            'success' => true,
            'message' => 'Evaluacion y pretension guardadas correctamente',
            'data' => $evaluacion,
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
