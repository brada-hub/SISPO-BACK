<?php

namespace App\Http\Controllers;

use App\Models\Postulacion;
use Illuminate\Http\Request;

class PostulacionController extends Controller
{
    public function index(Request $request)
    {
        $query = Postulacion::with(['postulante', 'oferta.cargo', 'oferta.sede']);

        if ($request->has('convocatoria_id')) {
            $query->whereHas('oferta', function($q) use ($request) {
                $q->where('convocatoria_id', $request->convocatoria_id);
            });
        }

        return $query->orderBy('id', 'desc')->get();
    }

    public function show($id)
    {
        return Postulacion::with(['postulante', 'oferta.cargo', 'oferta.sede'])->findOrFail($id);
    }

    /**
     * Update the status of a postulation
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'estado' => 'required|in:enviada,en_revision,validada,observada,rechazada'
        ]);

        $postulacion = Postulacion::findOrFail($id);
        $postulacion->estado = $validated['estado'];
        $postulacion->save();

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado correctamente',
            'estado' => $postulacion->estado
        ]);
    }

    /**
     * Get full detail for the case file (Expediente)
     */
    public function expediente($id)
    {
        $postulacion = Postulacion::with([
            'postulante.meritos.tipoDocumento',
            'postulante.meritos.archivos',
            'oferta.cargo',
            'oferta.sede',
            'oferta.convocatoria'
        ])->findOrFail($id);

        return response()->json($postulacion);
    }

    /**
     * Export applicants of a specific convocatoria or all
     */
    public function export($convocatoriaId = null)
    {
        $query = Postulacion::with(['postulante', 'oferta.cargo', 'oferta.sede']);

        if ($convocatoriaId) {
            $query->whereHas('oferta', function($q) use ($convocatoriaId) {
                $q->where('convocatoria_id', $convocatoriaId);
            });
        }

        $postulaciones = $query->get();

        $csv = "ID,Postulante,CI,Celular,Email,Cargo,Sede,Estado,Fecha\n";

        foreach ($postulaciones as $p) {
            $nombre = $p->postulante->nombres . ' ' . $p->postulante->apellidos;
            $fecha = $p->fecha_postulacion->format('d/m/Y');
            $csv .= "{$p->id},\"{$nombre}\",\"{$p->postulante->ci}\",\"{$p->postulante->celular}\",\"{$p->postulante->email}\",\"{$p->oferta->cargo->nombre}\",\"{$p->oferta->sede->nombre}\",\"{$p->estado}\",\"{$fecha}\"\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="postulantes.csv"');
    }
}
