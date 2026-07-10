<?php

namespace App\Services\Evaluation\Score;

use App\Models\Postulante;
use App\Models\ConvocatoriaScoreRule;

class TeachingExperienceScoreCalculator
{
    /**
     * Calculate score for teaching experience.
     */
    public function calculate(Postulante $postulante, ConvocatoriaScoreRule $rule): array
    {
        $maxPoints = (float) $rule->max_points;
        $score = 0.0;
        
        $docencias = $postulante->experienciasDocencia;
        $totalYears = 0;
        $recencySum = 0.0;
        $validRecords = 0;

        foreach ($docencias as $d) {
            if ($d->years_in_last_5_years !== null) {
                $totalYears += $d->years_in_last_5_years;
                $recencySum += $d->recency_score !== null ? (float) $d->recency_score : 50.0;
                $validRecords++;
            }
        }

        if ($totalYears > 0) {
            // 20% max points per active year in last 5 years
            $basePoints = $maxPoints * min(1.0, ($totalYears * 0.20));
            
            // Average recency score of active records
            $avgRecency = $validRecords > 0 ? ($recencySum / $validRecords) : 50.0;
            
            $score = $basePoints * ($avgRecency / 100.0);
        }

        $finalScore = min($maxPoints, round($score, 2));
        $reason = $totalYears > 0 
            ? "{$totalYears} años activos de docencia universitaria en últimos 5 años (distribuidos en {$validRecords} registros)."
            : "No se registran antecedentes de docencia universitaria activa en últimos 5 años.";

        return [
            'score'      => $finalScore,
            'max_points' => $maxPoints,
            'reason'     => $reason,
            'details'    => [
                'anios_activos_docencia' => $totalYears,
                'cantidad_registros'     => $validRecords,
            ]
        ];
    }
}
