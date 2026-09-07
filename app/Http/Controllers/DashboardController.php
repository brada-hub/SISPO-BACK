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
        $totalConvocatorias = Convocatoria::count();
        $postulacionesHoy = $qHoy->count();
        $postulacionesSemana = Postulacion::where('created_at', '>=', $hoy->copy()->subDays(7))->count();
        $pendientes = (clone $qTotal)->whereIn('estado', ['enviada', 'pendiente_archivos'])->count();
        $evaluadas = (clone $qTotal)->whereIn('estado', ['en_revision', 'evaluado', 'habilitado', 'seleccionado', 'rechazada'])->count();
        $avanceEvaluacion = $totalPostulaciones > 0 ? round(($evaluadas / $totalPostulaciones) * 100, 1) : 0;

        // Clasificaciones y Funnel de Selección
        $aptosCount = (clone $qTotal)->where('estado', 'habilitado')->count();
        if ($aptosCount === 0) {
            $aptosCount = \App\Models\EvaluationResult::where('classification', 'apto')->count();
        }
        $enRevisionCount = (clone $qTotal)->where('estado', 'en_revision')->count();
        $seleccionadosCount = (clone $qTotal)->where('estado', 'seleccionado')->count();
        $rechazadasCount = (clone $qTotal)->where('estado', 'rechazada')->count();
        $tasaHabilitacion = $evaluadas > 0 ? round(($aptosCount / $evaluadas) * 100, 1) : 0;

        $funnel = [
            ['key' => 'enviada', 'label' => 'Por Evaluar', 'count' => $pendientes, 'color' => '#6366F1', 'icon' => 'hourglass_empty'],
            ['key' => 'en_revision', 'label' => 'En Evaluación', 'count' => $enRevisionCount, 'color' => '#8B5CF6', 'icon' => 'manage_search'],
            ['key' => 'apto', 'label' => 'Aptos / Habilitados', 'count' => $aptosCount, 'color' => '#10B981', 'icon' => 'verified'],
            ['key' => 'seleccionado', 'label' => 'Seleccionados', 'count' => $seleccionadosCount, 'color' => '#059669', 'icon' => 'military_tech'],
            ['key' => 'rechazada', 'label' => 'No Habilitados', 'count' => $rechazadasCount, 'color' => '#EF4444', 'icon' => 'highlight_off'],
        ];

        // 2. Por Sede (Distribución)
        $sedeQuery = DB::table('postulaciones')
            ->join('ofertas', 'ofertas.id', '=', 'postulaciones.oferta_id')
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
                'postulaciones_count' => (int)$row->postulaciones_count,
            ];
        })->sortByDesc('postulaciones_count')->values();

        // 3. Cargos Postulados (Top 8)
        $qOfertaStats = Oferta::query();
        if ($this->shouldLimitByConvocatoria($user)) {
            $qOfertaStats->whereIn('convocatoria_id', $allowedConvocatorias);
        } elseif (!empty($allowedSedes)) {
            $qOfertaStats->whereIn('sede_id', $allowedSedes);
        }

        $topOfertas = $qOfertaStats->withCount('postulaciones')
            ->with(['cargo:id,nombre', 'sede:id_sede,nombre'])
            ->orderBy('postulaciones_count', 'desc')
            ->take(8)
            ->get();

        $cargosPostulados = $topOfertas->map(function($oferta) {
            $cargoNombre = $oferta->cargo->nombre ?? 'Cargo #' . $oferta->cargo_id;
            $sedeNombre = $oferta->sede->nombre ?? 'Sede #' . $oferta->sede_id;
            return [
                'nombre' => $cargoNombre . ' (' . $sedeNombre . ')',
                'cargo' => $cargoNombre,
                'sede' => $sedeNombre,
                'postulaciones_count' => (int)$oferta->postulaciones_count
            ];
        });

        // 4. Convocatorias Operativas y Cierres Próximos
        $qConvocatorias = Convocatoria::query();
        if ($this->shouldLimitByConvocatoria($user)) {
            $qConvocatorias->whereIn('id', $allowedConvocatorias);
        } elseif (!empty($allowedSedes)) {
            $qConvocatorias->whereHas('ofertas', fn($q) => $q->whereIn('sede_id', $allowedSedes));
        }

        $convocatoriasGestion = (clone $qConvocatorias)
            ->withCount(['postulaciones', 'postulaciones as evaluadas_count' => function($q) {
                $q->whereIn('estado', ['en_revision', 'evaluado', 'habilitado', 'seleccionado', 'rechazada']);
            }])
            ->with(['ofertas.sede:id_sede,nombre', 'ofertas.cargo:id,nombre'])
            ->orderBy('fecha_cierre', 'desc')
            ->take(8)
            ->get()
            ->map(function($c) use ($hoy) {
                $fechaCierre = $c->fecha_cierre ? Carbon::parse($c->fecha_cierre) : null;
                $diasRestantes = $fechaCierre ? (int)$hoy->diffInDays($fechaCierre, false) : 0;
                $total = (int)($c->postulaciones_count ?? 0);
                $evaluadas = (int)($c->evaluadas_count ?? 0);
                $avance = $total > 0 ? round(($evaluadas / $total) * 100) : 0;
                $sedes = $c->ofertas->map(fn($o) => $o->sede->nombre ?? null)->filter()->unique()->values();

                return [
                    'id' => $c->id,
                    'titulo' => $c->titulo,
                    'codigo' => $c->codigo ?? 'CONV-' . $c->id,
                    'fecha_inicio' => $c->fecha_inicio ? Carbon::parse($c->fecha_inicio)->format('d/m/Y') : '---',
                    'fecha_cierre' => $fechaCierre ? $fechaCierre->format('d/m/Y') : '---',
                    'dias_restantes' => $diasRestantes,
                    'is_activa' => $diasRestantes >= 0,
                    'is_urgente' => $diasRestantes >= 0 && $diasRestantes <= 5,
                    'postulaciones_count' => $total,
                    'evaluadas_count' => $evaluadas,
                    'avance_pct' => $avance,
                    'sedes' => $sedes
                ];
            });

        // 5. Timeline de Postulaciones (Tendencia últimos 15 días activos)
        $timelineRaw = DB::table('postulaciones')
            ->select(DB::raw('DATE(created_at) as dia'), DB::raw('count(*) as total'))
            ->groupBy('dia')
            ->orderBy('dia', 'desc')
            ->take(15)
            ->get()
            ->reverse()
            ->values();

        $timeline = $timelineRaw->map(function($t) {
            return [
                'fecha' => Carbon::parse($t->dia)->format('d M'),
                'count' => (int)$t->total
            ];
        });

        // 6. Actividad Reciente Detallada
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
                'total_convocatorias' => $totalConvocatorias,
                'hoy' => $postulacionesHoy,
                'semana' => $postulacionesSemana,
                'pendientes' => $pendientes,
                'evaluadas' => $evaluadas,
                'avance_evaluacion' => $avanceEvaluacion,
                'aptos' => $aptosCount,
                'tasa_habilitacion' => $tasaHabilitacion,
            ],
            'funnel' => $funnel,
            'timeline' => $timeline,
            'chart_sede' => $porSede,
            'chart_cargos' => $cargosPostulados,
            'convocatorias_gestion' => $convocatoriasGestion,
            'cierres_criticos' => $convocatoriasGestion->where('is_urgente', true)->values(),
            'recientes' => $actividadReciente->map(function($p) {
                $post = $p->postulante;
                $puntuacion = $p->evaluacion->puntuacion_total ?? null;
                return [
                    'id' => $p->id,
                    'postulante' => $post ? ($post->nombres . ' ' . $post->apellidos) : 'Postulante no identificado',
                    'ci' => $post ? ($post->ci . ' ' . ($post->ci_expedido ?? '')) : '',
                    'foto' => $post?->foto_perfil_path ?? null,
                    'cargo' => $p->oferta->cargo->nombre ?? 'Cargo N/A',
                    'sede' => $p->oferta->sede->nombre ?? 'Sede N/A',
                    'convocatoria' => $p->oferta->convocatoria->titulo ?? 'General',
                    'estado' => $p->estado ?? 'enviada',
                    'puntuacion' => $puntuacion !== null ? (float)$puntuacion : null,
                    'fecha' => $p->created_at ? $p->created_at->diffForHumans() : 'Fecha N/A',
                    'fecha_exacta' => $p->created_at ? $p->created_at->format('d/m/Y H:i') : ''
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
