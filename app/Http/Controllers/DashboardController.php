<?php

namespace App\Http\Controllers;

use App\Models\Postulacion;
use App\Models\Convocatoria;
use App\Models\Oferta;
use App\Models\Sede;
use App\Models\Cargo;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats()
    {
        $hoy = Carbon::today();

        // 1. KPI Totals
        $totalPostulaciones = Postulacion::count();
        $convocatoriasActivas = Convocatoria::whereDate('fecha_inicio', '<=', $hoy)
            ->whereDate('fecha_cierre', '>=', $hoy)
            ->count();
        $postulacionesHoy = Postulacion::whereDate('created_at', $hoy)->count();
        $pendientes = Postulacion::where('estado', 'enviada')->count();

        // 2. Por Sede (Distribución) - Solo sedes con convocatorias activas
        $porSede = Sede::whereHas('ofertas.convocatoria', function($q) use ($hoy) {
            $q->whereDate('fecha_inicio', '<=', $hoy)
              ->whereDate('fecha_cierre', '>=', $hoy);
        })
        ->withCount(['postulaciones as postulaciones_count' => function($q) use ($hoy) {
             // Opcional: ¿Contamos solo las de convocatorias activas?
             // Por el comentario del usuario, parece que sí
             $q->whereHas('oferta.convocatoria', function($cq) use ($hoy) {
                 $cq->whereDate('fecha_inicio', '<=', $hoy)
                   ->whereDate('fecha_cierre', '>=', $hoy);
             });
        }])
        ->get(['id', 'nombre']);

        // 3. Cargos Postulados (Combinación Cargo - Sede) - Solo de convocatorias activas
        $topOfertas = Oferta::whereHas('convocatoria', function($q) use ($hoy) {
            $q->whereDate('fecha_inicio', '<=', $hoy)
              ->whereDate('fecha_cierre', '>=', $hoy);
        })
        ->withCount(['postulaciones as postulaciones_count' => function($q) use ($hoy) {
             $q->whereHas('oferta.convocatoria', function($cq) use ($hoy) {
                 $cq->whereDate('fecha_inicio', '<=', $hoy)
                   ->whereDate('fecha_cierre', '>=', $hoy);
             });
        }])
        ->with(['cargo:id,nombre', 'sede:id,nombre'])
        ->orderBy('postulaciones_count', 'desc')
        ->take(10) // Tomamos un poco más para que se vea variado
        ->get();

        $cargosPostulados = $topOfertas->map(function($oferta) {
            return [
                'nombre' => $oferta->cargo->nombre . ' - ' . $oferta->sede->nombre,
                'postulaciones_count' => $oferta->postulaciones_count
            ];
        });

        // 4. Próximos Cierres (Próximos 7 días)
        $proximosCierres = Convocatoria::whereDate('fecha_cierre', '>=', $hoy)
            ->whereDate('fecha_cierre', '<=', $hoy->copy()->addDays(7))
            ->orderBy('fecha_cierre', 'asc')
            ->take(5)
            ->get();

        // 5. Actividad Reciente
        $actividadReciente = Postulacion::with(['postulante:id,nombres,apellidos', 'oferta.cargo:id,nombre', 'oferta.sede:id,nombre'])
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'kpis' => [
                'total' => $totalPostulaciones,
                'activas' => $convocatoriasActivas,
                'hoy' => $postulacionesHoy,
                'pendientes' => $pendientes
            ],
            'chart_sede' => $porSede,
            'chart_cargos' => $cargosPostulados,
            'cierres_criticos' => $proximosCierres,
            'recientes' => $actividadReciente
        ]);
    }
}
