<?php

namespace App\Services\Evaluation\Score;

use App\Models\Postulante;
use App\Models\ConvocatoriaScoreRule;

class ProfessionalExperienceScoreCalculator
{
    /**
     * Calculate score for professional experience.
     */
    public function calculate(Postulante $postulante, ConvocatoriaScoreRule $rule): array
    {
        $maxPoints = (float) $rule->max_points;
        $score = 0.0;

        $summary = $postulante->experienceSummary;
        $uniqueMonths = $summary ? $summary->unique_months_last_5_years : 0;
        $recencyScore = $summary && $summary->max_recency_score !== null ? (float) $summary->max_recency_score : 50.0;
        $overlapMonths = $summary ? $summary->overlap_months_estimated : 0;

        if ($uniqueMonths > 0) {
            $basePoints = 0.0;

            if ($uniqueMonths > 36) {
                $basePoints = $maxPoints;  // > 3 years experience in last 5 years
            } elseif ($uniqueMonths >= 13) {
                $basePoints = $maxPoints * 0.70;  // 1-3 years experience
            } else {
                $basePoints = $maxPoints * 0.30;  // <= 1 year experience
            }

            // Multiply by recency factor
            $score = $basePoints * ($recencyScore / 100.0);
        }

        $finalScore = min($maxPoints, round($score, 2));
        $reason = "{$uniqueMonths} meses únicos de experiencia profesional en últimos 5 años, con un factor de recencia del {$recencyScore}%. Se mitigaron {$overlapMonths} meses solapados.";

        return [
            'score'      => $finalScore,
            'max_points' => $maxPoints,
            'reason'     => $reason,
            'details'    => [
                'meses_unicos_last_5_years' => $uniqueMonths,
                'max_recency_score'         => $recencyScore,
                'solapamiento_detectado'    => $summary ? $summary->overlap_detected : false,
                'meses_solapados_mitigados' => $overlapMonths,
            ]
        ];
    }
}
