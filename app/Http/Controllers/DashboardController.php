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
        $allowedConvocatorias = $this->allowedConvocatoriaIds($user);
        $allowedSedes = $this->allowedSedeIds($user);

        $qTotal = Postulacion::query();
        $qActivas = Convocatoria::whereDate('fecha_inicio', '<=', $hoy)->whereDate('fecha_cierre', '>=', $hoy);
        $qHoy = Postulacion::whereDate('created_at', $hoy);
        $qPendientes = Postulacion::where('estado', 'enviada');

        if ($this->shouldLimitByConvocatoria($user)) {
            $qTotal->whereHas('oferta', fn($q) => $q->whereIn('convocatoria_id', $allowedConvocatorias));
            $qActivas->whereIn('id', $allowedConvocatorias);
            $qHoy->whereHas('oferta', fn($q) => $q->whereIn('convocatoria_id', $allowedConvocatorias));
            $qPendientes->whereHas('oferta', fn($q) => $q->whereIn('convocatoria_id', $allowedConvocatorias));
        } elseif (!empty($allowedSedes)) {
            $qTotal->whereHas('oferta', fn($q) => $q->whereIn('sede_id', $allowedSedes));
            $qActivas->whereHas('ofertas', fn($q) => $q->whereIn('sede_id', $allowedSedes));
            $qHoy->whereHas('oferta', fn($q) => $q->whereIn('sede_id', $allowedSedes));
            $qPendientes->whereHas('oferta', fn($q) => $q->whereIn('sede_id', $allowedSedes));
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

        if ($this->shouldLimitByConvocatoria($user)) {
            $sedeQuery->whereIn('ofertas.convocatoria_id', $allowedConvocatorias);
        } elseif (!empty($allowedSedes)) {
            $sedeQuery->whereIn('ofertas.sede_id', $allowedSedes);
        }

        $conteosRaw = $sedeQuery->get();
        $sedeIds = $conteosRaw->pluck('sede_id')->filter()->unique()->toArray();
        $sedesInfo = Sede::whereIn('id_sede', $sedeIds)->get(['id_sede', 'nombre'])->keyBy('id_sede');

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
        if ($this->shouldLimitByConvocatoria($user)) {
            $qOfertaStats->whereIn('convocatoria_id', $allowedConvocatorias);
        } elseif (!empty($allowedSedes)) {
            $qOfertaStats->whereIn('sede_id', $allowedSedes);
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
        ->with(['cargo:id,nombre', 'sede:id_sede,nombre'])
        ->orderBy('postulaciones_count', 'desc')
        ->take(10)
        ->get();

        $cargosPostulados = $topOfertas->map(function($oferta) {
            $cargoNombre = $oferta->cargo->nombre ?? 'Cargo #' . $oferta->cargo_id;
            $sedeNombre = $oferta->sede->nombre ?? 'Sede #' . $oferta->sede_id;
            return [
                'nombre' => $cargoNombre . ' - ' . $sedeNombre,
                'postulaciones_count' => $oferta->postulaciones_count
            ];
        });

        // 4. Próximos Cierres (Próximos 7 días)
        $qCierres = Convocatoria::query();
        if ($this->shouldLimitByConvocatoria($user)) {
            $qCierres->whereIn('id', $allowedConvocatorias);
        } elseif (!empty($allowedSedes)) {
            $qCierres->whereHas('ofertas', fn($q) => $q->whereIn('sede_id', $allowedSedes));
        }

        $proximosCierres = $qCierres->whereDate('fecha_cierre', '>=', $hoy)
            ->whereDate('fecha_cierre', '<=', $hoy->copy()->addDays(7))
            ->orderBy('fecha_cierre', 'asc')
            ->take(5)
            ->get();

        // 5. Actividad Reciente
        $qReciente = Postulacion::query();
        $query = Postulacion::with(['postulante', 'oferta.cargo', 'oferta.sede', 'oferta.convocatoria', 'evaluacion']);
        if ($this->shouldLimitByConvocatoria($user)) {
            $query->whereHas('oferta', fn($q) => $q->whereIn('convocatoria_id', $allowedConvocatorias));
        } elseif (!empty($allowedSedes)) {
            $query->whereHas('oferta', fn($q) => $q->whereIn('sede_id', $allowedSedes));
        }

        $actividadReciente = $query->latest()
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
            'recientes' => $actividadReciente->map(function($p) {
                return [
                    'id' => $p->id,
                    'postulante' => $p->postulante->nombres . ' ' . $p->postulante->apellidos,
                    'cargo' => $p->oferta->cargo->nombre ?? 'Cargo N/A',
                    'sede' => $p->oferta->sede->nombre ?? 'Sede N/A',
                    'fecha' => $p->created_at->diffForHumans(),
                ];
            })
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
