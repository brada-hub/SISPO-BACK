<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EvaluacionPostulacion;
use Illuminate\Support\Facades\Auth;

class EvaluacionMeritoController extends Controller
{
    public function showByPostulacion($postulacionId)
    {
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
        ]);

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

        // Update postulation status to en_revision if it was 'enviada'
        $postulacion = \App\Models\Postulacion::find($validated['postulacion_id']);
        if ($postulacion->estado === 'enviada') {
            $postulacion->estado = 'en_revision';
            $postulacion->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Evaluación guardada correctamente',
            'data' => $evaluacion
        ]);
    }
}
