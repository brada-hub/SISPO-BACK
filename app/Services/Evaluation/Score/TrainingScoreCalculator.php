<?php

namespace App\Services\Evaluation\Score;

use App\Models\Postulante;
use App\Models\ConvocatoriaScoreRule;

class TrainingScoreCalculator
{
    /**
     * Calculate score for trainings/courses.
     */
    public function calculate(Postulante $postulante, ConvocatoriaScoreRule $rule): array
    {
        $maxPoints = (float) $rule->max_points;
        $score = 0.0;

        // Load pre-calculated temporal summary
        $summary = $postulante->trainingSummary;

        $totalHours = $summary ? $summary->total_training_hours : 0;
        $hoursLast5Years = $summary ? $summary->training_hours_last_5_years : 0;
        $maxRecency = $summary && $summary->max_training_recency_score !== null ? (float) $summary->max_training_recency_score : 0.0;
        $coursesCount = $summary ? $summary->total_training_records : 0;

        // Fallback if summary is not populated yet
        if (!$summary) {
            $capacitaciones = $postulante->capacitaciones;
            $coursesCount = $capacitaciones->count();
            foreach ($capacitaciones as $cap) {
                $totalHours += (int) $cap->carga_horaria;
            }
            $hoursLast5Years = $totalHours; // assumption fallback
            $maxRecency = $coursesCount > 0 ? 50.0 : 0.0;
        }

        // Sub-sections points split (70% recent hours, 20% max recency, 10% historical total volume)
        $maxRecentHoursPts = $maxPoints * 0.70;
        $maxRecencyPts     = $maxPoints * 0.20;
        $maxHistVolumePts  = $maxPoints * 0.10;

        // 1. Hours last 5 years: 1 point per 10 hours
        $recentHoursPts = min($maxRecentHoursPts, ($hoursLast5Years / 10.0));

        // 2. Recency score: max points * percentage attained
        $recencyPts = $maxRecencyPts * ($maxRecency / 100.0);

        // 3. Historical total volume: 1 point per 10 hours
        $histVolumePts = min($maxHistVolumePts, ($totalHours / 10.0));

        // Total score sum
        $score = $recentHoursPts + $recencyPts + $histVolumePts;
        $finalScore = min($maxPoints, round($score, 2));

        // Human-readable mathematical reason
        $reason = "Capacitación: {$totalHours} horas totales, {$hoursLast5Years} horas en últimos 5 años, recencia máxima de {$maxRecency}%. Score calculado con 70% horas recientes, 20% recencia y 10% volumen histórico.";

        return [
            'score'      => $finalScore,
            'max_points' => $maxPoints,
            'reason'     => $reason,
            'details'    => [
                'total_cursos'          => $coursesCount,
                'total_horas'           => $totalHours,
                'horas_ultimos_5_anios' => $hoursLast5Years,
                'recencia_maxima'       => $maxRecency,
            ]
        ];
    }
}
