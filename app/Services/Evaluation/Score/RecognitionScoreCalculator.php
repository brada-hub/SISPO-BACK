<?php

namespace App\Services\Evaluation\Score;

use App\Models\Postulante;
use App\Models\ConvocatoriaScoreRule;

class RecognitionScoreCalculator
{
    /**
     * Calculate score for recognitions and awards.
     */
    public function calculate(Postulante $postulante, ConvocatoriaScoreRule $rule): array
    {
        $maxPoints = (float) $rule->max_points;
        $score = 0.0;

        $reconocimientos = $postulante->reconocimientos;
        $count = $reconocimientos->count();

        if ($count > 0) {
            $score = $count * 5.0;
        }

        $finalScore = min($maxPoints, round($score, 2));
        $reason = $count > 0 
            ? "{$count} reconocimientos o distinciones honoríficas registradas (5 pts por ítem, cappeado a {$maxPoints} pts)."
            : "No se registran reconocimientos o distinciones honoríficas.";

        return [
            'score'      => $finalScore,
            'max_points' => $maxPoints,
            'reason'     => $reason,
            'details'    => [
                'total_reconocimientos' => $count,
            ]
        ];
    }
}
