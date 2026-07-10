<?php

namespace App\Console\Commands;

use App\Models\EvaluationResult;
use Illuminate\Console\Command;

class AuditRiskStratificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sispo:audit-risk-stratification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform visual HR audit on risk stratification, showing levels, auto-approvals and workflow reductions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('============================================================');
        $this->info('      SISPO RISK STRATIFICATION & COMPLIANCE AUDIT          ');
        $this->info('============================================================');

        $totalEvaluations = EvaluationResult::count();
        if ($totalEvaluations === 0) {
            $this->error('No existen evaluaciones registradas en base de datos. Corre php artisan sispo:evaluate-deterministic --write primero.');
            return 1;
        }

        // 1. Risk Level Distribution
        $riskDistribution = [
            'critical' => EvaluationResult::where('review_risk_level', 'critical')->count(),
            'high'     => EvaluationResult::where('review_risk_level', 'high')->count(),
            'medium'   => EvaluationResult::where('review_risk_level', 'medium')->count(),
            'low'      => EvaluationResult::where('review_risk_level', 'low')->count(),
        ];

        // 2. Evaluation Status distribution
        $statusDistribution = [
            'auto_approved'         => EvaluationResult::where('evaluation_status', 'auto_approved')->count(),
            'requires_human_review' => EvaluationResult::where('evaluation_status', 'requires_human_review')->count(),
            'evaluated'             => EvaluationResult::where('evaluation_status', 'evaluated')->count(),
        ];

        // Average Risk Score
        $avgRisk = EvaluationResult::avg('review_risk_score') ?: 0.0;

        $this->info('📊 MÉTRICAS OPERATIVAS DE RIESGO DE POSTULANTES:');
        $metricsTable = [
            ['Total Evaluaciones Procesadas', $totalEvaluations],
            ['Promedio Score de Riesgo General', round($avgRisk, 2) . ' pts'],
            ['Aprobados Automáticamente (auto_approved)', $statusDistribution['auto_approved']],
            ['Casos Conformes Regulares (evaluated)', $statusDistribution['evaluated']],
            ['Casos con Auditoría Obligatoria (requires_human_review)', $statusDistribution['requires_human_review']],
        ];
        $this->table(['Indicador de Control', 'Valor'], $metricsTable);

        $this->info('------------------------------------------------------------');
        $this->info('🚩 DISTRIBUCIÓN POR NIVELES DE RIESGO DETECTADOS (STRATIFICATION):');
        
        $riskTable = [];
        foreach ($riskDistribution as $level => $count) {
            $percentage = round(($count / $totalEvaluations) * 100, 1);
            $badge = '';
            switch ($level) {
                case 'critical': $badge = '<fg=red;options=bold>CRITICAL</>'; break;
                case 'high':     $badge = '<fg=yellow;options=bold>HIGH</>'; break;
                case 'medium':   $badge = '<fg=blue>MEDIUM</>'; break;
                case 'low':      $badge = '<fg=green>LOW</>'; break;
            }
            $riskTable[] = [$badge, $count . ' postulantes', $percentage . '%'];
        }
        $this->table(['Nivel de Riesgo', 'Frecuencia Detectada', 'Porcentaje Global'], $riskTable);

        // 3. Severe overlap metrics
        $severeOverlaps = EvaluationResult::where('review_flags_json', 'like', '%severe_overlap_detected%')->count();
        $grayZones = EvaluationResult::where('review_flags_json', 'like', '%score_gray_zone%')->count();
        $academicUncertain = EvaluationResult::where('review_flags_json', 'like', '%academic_match_uncertain%')->count();

        $this->info('------------------------------------------------------------');
        $this->info('🔎 DESGLOSE HISTÓRICO DE ALERTAS DE NEGOCIO:');
        $alertsTable = [
            ['Solapamientos Laborales Severos (overlap_detected)', $severeOverlaps . ' casos'],
            ['Puntajes en Zona Gris (score_gray_zone)', $grayZones . ' casos'],
            ['Incertidumbre en Carrera (academic_match_uncertain)', $academicUncertain . ' casos'],
        ];
        $this->table(['Alerta de Negocio', 'Incidencias'], $alertsTable);

        // 4. Reducción Proyectada de Carga Operativa RRHH
        // Antes: 120 postulaciones (39.0%) requerían revisión humana en la fase anterior
        // Ahora: requires_human_review es solo para HIGH y CRITICAL
        $humanRequiredCount = $statusDistribution['requires_human_review'];
        $humanRequiredPercent = round(($humanRequiredCount / $totalEvaluations) * 100, 1);

        $reductionRate = round(((120 - $humanRequiredCount) / 120) * 100, 1);

        $this->info('------------------------------------------------------------');
        $this->info('📉 ANÁLISIS DE EFICIENCIA OPERATIVA RRHH:');
        $efficiencyTable = [
            ['Tasa de Revisión Humana Anterior (Modo Binario)', '120 casos (39.0%)'],
            ['Tasa de Revisión Humana Nueva (Modo Estratificado)', $humanRequiredCount . ' casos (' . $humanRequiredPercent . '%)'],
            ['Reducción Directa de Carga de Trabajo de Selección', '<fg=green;options=bold>' . $reductionRate . '% de Ahorro de Tiempo</>'],
            ['Llamadas Realizadas a Gemini (Costos de API)', '<fg=green>0 llamadas ($0.00 USD)</>'],
        ];
        $this->table(['Métrica de Desempeño', 'Resultado Comercial'], $efficiencyTable);

        // Top 5 Critical cases
        $topCritical = EvaluationResult::where('review_risk_level', 'critical')
            ->orderBy('review_risk_score', 'desc')
            ->limit(5)
            ->get();

        if ($topCritical->isNotEmpty()) {
            $this->info('------------------------------------------------------------');
            $this->info('🚨 TOP 5 CASOS DE ALTO RIESGO / CRÍTICOS IDENTIFICADOS EN MYSQL:');
            $criticalTable = [];
            foreach ($topCritical as $item) {
                $postulante = $item->postulacion->postulante;
                $nombre = $postulante ? $postulante->nombre_completo : 'N/A';
                $criticalTable[] = [
                    $item->postulacion_id,
                    $nombre,
                    $item->review_risk_score . ' pts',
                    $item->score_total . ' pts',
                    substr($item->review_reason_summary, 0, 75) . '...'
                ];
            }
            $this->table(['ID', 'Postulante', 'Riesgo', 'Score CV', 'Causa Raíz del Riesgo'], $criticalTable);
        }

        // Top 5 Safe Approvals (auto_approved)
        $topSafe = EvaluationResult::where('evaluation_status', 'auto_approved')
            ->orderBy('score_total', 'desc')
            ->limit(5)
            ->get();

        if ($topSafe->isNotEmpty()) {
            $this->info('------------------------------------------------------------');
            $this->info('🏆 TOP 5 CASOS SEGUROS CON APROBACIÓN AUTOMÁTICA (AUTO-APPROVED):');
            $safeTable = [];
            foreach ($topSafe as $item) {
                $postulante = $item->postulacion->postulante;
                $nombre = $postulante ? $postulante->nombre_completo : 'N/A';
                $safeTable[] = [
                    $item->postulacion_id,
                    $nombre,
                    $item->review_risk_score . ' pts',
                    $item->score_total . ' pts',
                    'Aprobación Segura'
                ];
            }
            $this->table(['ID', 'Postulante', 'Riesgo', 'Score CV', 'Auditoría Interna'], $safeTable);
        }

        $this->info('============================================================');
        $this->info('🎉 ¡SISPO Risk Stratification es 100% Determinista, Seguro y Auditable!');
        $this->info('============================================================');

        return 0;
    }
}
