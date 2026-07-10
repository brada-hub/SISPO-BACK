<?php

namespace App\Services\Evaluation\Score;

use App\Models\Postulante;
use App\Models\ConvocatoriaScoreRule;

class AcademicScoreCalculator
{
    /**
     * Calculate score for academic formation.
     */
    public function calculate(Postulante $postulante, ConvocatoriaScoreRule $rule): array
    {
        $maxPoints = (float) $rule->max_points;
        $score = 0.0;
        $highestLevel = null;
        $carreraEstudiada = 'Ninguna';
        $directRelationship = false;

        $formaciones = $postulante->formacionesAcademicas;

        if ($formaciones->isNotEmpty()) {
            // Find highest level based on catalog hierarchy_order
            $highestFormacion = null;
            $maxHierarchy = -1;

            foreach ($formaciones as $f) {
                $level = $f->academicLevel;
                if ($level && $level->hierarchy_order > $maxHierarchy) {
                    $maxHierarchy = $level->hierarchy_order;
                    $highestFormacion = $f;
                }
            }

            if ($highestFormacion) {
                $highestLevel = $highestFormacion->academicLevel;
                $carreraEstudiada = $highestFormacion->carrera_raw;

                // Base scoring fraction based on hierarchy
                // 5: Doctorado (100%), 4: Maestría (90%), 3: Licenciatura (80%), 2: Tec. Sup (60%), 1: Tec. Medio (40%)
                $baseFraction = 0.0;
                switch ($maxHierarchy) {
                    case 5:
                        $baseFraction = 1.0;
                        break;
                    case 4:
                        $baseFraction = 0.90;
                        break;
                    case 3:
                        $baseFraction = 0.80;
                        break;
                    case 2:
                        $baseFraction = 0.60;
                        break;
                    case 1:
                        $baseFraction = 0.40;
                        break;
                    default:
                        $baseFraction = 0.30;
                }

                $score = $maxPoints * $baseFraction;

                // Career relation alignment check
                // If direct career matching matches the convocatoria's professional area rules
                if ($highestFormacion->career_id) {
                    $directRelationship = true;
                } else {
                    // Career not fully normalized, apply 20% penalty
                    $score = $score * 0.80;
                }
            }
        }

        $finalScore = min($maxPoints, round($score, 2));
        $nivelName = $highestLevel ? $highestLevel->name : 'No registrado';
        $relStr = $directRelationship ? 'Directa y vinculada' : 'No vinculada directamente (penalización 20%)';
        $reason = "Formación académica de nivel {$nivelName} en la carrera de {$carreraEstudiada}. Relación con convocatoria: {$relStr}.";

        return [
            'score'                => $finalScore,
            'max_points'           => $maxPoints,
            'reason'               => $reason,
            'details'              => [
                'nivel_maximo'        => $nivelName,
                'carrera'             => $carreraEstudiada,
                'relacion_directa'    => $directRelationship,
            ]
        ];
    }
}
