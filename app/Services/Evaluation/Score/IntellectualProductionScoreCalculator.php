<?php

namespace App\Services\Evaluation\Score;

use App\Models\Postulante;
use App\Models\ConvocatoriaScoreRule;

class IntellectualProductionScoreCalculator
{
    /**
     * Calculate score for intellectual production (publications, books).
     */
    public function calculate(Postulante $postulante, ConvocatoriaScoreRule $rule): array
    {
        $maxPoints = (float) $rule->max_points;
        $score = 0.0;
        $booksCount = 0;
        $articlesCount = 0;
        $othersCount = 0;

        $producciones = $postulante->produccionesIntelectuales;

        foreach ($producciones as $prod) {
            $type = mb_strtolower($prod->tipo_produccion, 'UTF-8');
            $points = 2.0; // generic fallback

            if (str_contains($type, 'libro')) {
                $points = 5.0;
                $booksCount++;
            } elseif (str_contains($type, 'revista') || str_contains($type, 'cientifico') || str_contains($type, 'articulo')) {
                $points = 4.0;
                $articlesCount++;
            } else {
                $othersCount++;
            }

            $score += $points;
        }

        $finalScore = min($maxPoints, round($score, 2));
        $reason = "Producción intelectual registrada: {$booksCount} libros, {$articlesCount} artículos indexados y {$othersCount} publicaciones generales (cappeado a {$maxPoints} pts).";

        return [
            'score'      => $finalScore,
            'max_points' => $maxPoints,
            'reason'     => $reason,
            'details'    => [
                'libros'     => $booksCount,
                'articulos'  => $articlesCount,
                'otros'      => $othersCount,
            ]
        ];
    }
}
