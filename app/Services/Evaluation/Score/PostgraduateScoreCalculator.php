<?php

namespace App\Services\Evaluation\Score;

use App\Models\Postulante;
use App\Models\ConvocatoriaScoreRule;

class PostgraduateScoreCalculator
{
    /**
     * Calculate score for postgraduate studies.
     */
    public function calculate(Postulante $postulante, ConvocatoriaScoreRule $rule): array
    {
        $maxPoints = (float) $rule->max_points;
        $score = 0.0;
        $programsCount = 0;
        $breakdown = [];

        $postgrados = $postulante->formacionesPostgrado;

        foreach ($postgrados as $p) {
            $type = $p->postgraduateType;
            $points = 0.0;

            if ($type) {
                switch ($type->code) {
                    case 'doctorado':
                        $points = 10.0;
                        break;
                    case 'maestria':
                        $points = 8.0;
                        break;
                    case 'especialidad':
                        $points = 5.0;
                        break;
                    case 'diplomado':
                        $points = 3.0;
                        break;
                    default:
                        $points = 2.0;
                }
            } else {
                // If not fully normalized, fallback to generic points
                $points = 2.0;
            }

            $score += $points;
            $programsCount++;
            $breakdown[] = [
                'nombre' => $p->nombre_programa,
                'tipo'   => $type ? $type->name : $p->tipo_posgrado_raw,
                'puntos' => $points,
            ];
        }

        $finalScore = min($maxPoints, round($score, 2));
        $reason = "{$programsCount} programas de posgrado registrados con una puntuación acumulada de {$score} pts (cappeado a {$maxPoints} pts).";

        return [
            'score'      => $finalScore,
            'max_points' => $maxPoints,
            'reason'     => $reason,
            'details'    => [
                'total_programas' => $programsCount,
                'programas'       => $breakdown,
            ]
        ];
    }
}
