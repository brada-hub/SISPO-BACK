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
        $user = auth()->user();

        $qTotal = Postulacion::query();
        $qActivas = Convocatoria::whereDate('fecha_inicio', '<=', $hoy)->whereDate('fecha_cierre', '>=', $hoy);
        $qHoy = Postulacion::whereDate('created_at', $hoy);
        $qPendientes = Postulacion::where('estado', 'enviada');

        if ($user && !in_array($user->rol?->name, ['ADMINISTRADOR', 'SUPER ADMIN']) && $user->sede_id) {
            $qTotal->whereHas('oferta', fn($q) => $q->where('sede_id', $user->sede_id));
            $qActivas->whereHas('ofertas', fn($q) => $q->where('sede_id', $user->sede_id));
            $qHoy->whereHas('oferta', fn($q) => $q->where('sede_id', $user->sede_id));
            $qPendientes->whereHas('oferta', fn($q) => $q->where('sede_id', $user->sede_id));
        }

        $totalPostulaciones = $qTotal->count();
        $convocatoriasActivas = $qActivas->count();
        $postulacionesHoy = $qHoy->count();
        $pendientes = $qPendientes->count();

        // 2. Por Sede (Distribución) - Solo sedes con convocatorias activas
        // NOTA: Sede usa conexión 'core' (sso_db) pero ofertas/postulaciones están en sispo_db.
        // Se usa DB::table para evitar el problema de cross-connection.
        $sedeQuery = DB::table('postulaciones')
            ->join('ofertas', 'ofertas.id', '=', 'postulaciones.oferta_id')
            ->join('convocatorias', 'convocatorias.id', '=', 'ofertas.convocatoria_id')
            ->whereDate('convocatorias.fecha_inicio', '<=', $hoy)
            ->whereDate('convocatorias.fecha_cierre', '>=', $hoy)
            ->select('ofertas.sede_id', DB::raw('count(*) as postulaciones_count'))
            ->groupBy('ofertas.sede_id');

        if ($user && !in_array($user->rol?->name, ['ADMINISTRADOR', 'SUPER ADMIN']) && $user->sede_id) {
            $sedeQuery->where('ofertas.sede_id', $user->sede_id);
        }

        $conteosRaw = $sedeQuery->get();
        $sedeIds = $conteosRaw->pluck('sede_id')->toArray();
        $sedesInfo = Sede::whereIn('id', $sedeIds)->get(['id', 'nombre'])->keyBy('id');

        $porSede = $conteosRaw->map(function($row) use ($sedesInfo) {
            $sede = $sedesInfo[$row->sede_id] ?? null;
            return [
                'id' => $row->sede_id,
                'nombre' => $sede?->nombre ?? 'Sede #' . $row->sede_id,
                'postulaciones_count' => $row->postulaciones_count,
            ];
        });

        // 3. Cargos Postulados (Combinación Cargo - Sede) - Solo de convocatorias activas
        $qOfertaStats = Oferta::query();
        if ($user && !in_array($user->rol?->name, ['ADMINISTRADOR', 'SUPER ADMIN']) && $user->sede_id) {
            $qOfertaStats->where('sede_id', $user->sede_id);
        }

        $topOfertas = $qOfertaStats->whereHas('convocatoria', function($q) use ($hoy) {
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
        ->take(10)
        ->get();

        $cargosPostulados = $topOfertas->map(function($oferta) {
            return [
                'nombre' => $oferta->cargo->nombre . ' - ' . $oferta->sede->nombre,
                'postulaciones_count' => $oferta->postulaciones_count
            ];
        });

        // 4. Próximos Cierres (Próximos 7 días)
        $qCierres = Convocatoria::query();
        if ($user && !in_array($user->rol?->name, ['ADMINISTRADOR', 'SUPER ADMIN']) && $user->sede_id) {
            $qCierres->whereHas('ofertas', fn($q) => $q->where('sede_id', $user->sede_id));
        }

        $proximosCierres = $qCierres->whereDate('fecha_cierre', '>=', $hoy)
            ->whereDate('fecha_cierre', '<=', $hoy->copy()->addDays(7))
            ->orderBy('fecha_cierre', 'asc')
            ->take(5)
            ->get();

        // 5. Actividad Reciente
        $qReciente = Postulacion::query();
        if ($user && !in_array($user->rol?->name, ['ADMINISTRADOR', 'SUPER ADMIN']) && $user->sede_id) {
            $qReciente->whereHas('oferta', fn($q) => $q->where('sede_id', $user->sede_id));
        }

        $actividadReciente = $qReciente->with(['postulante:id,nombres,apellidos', 'oferta.cargo:id,nombre', 'oferta.sede:id,nombre'])
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
