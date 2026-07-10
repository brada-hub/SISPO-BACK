<?php

namespace App\Services\Evaluation\Review;

use App\Models\Postulante;
use Illuminate\Support\Facades\Log;

class ReviewDecisionService
{
    /**
     * Audit a postulant evaluation to decide if human review is required using Risk Stratification.
     */
    public function audit(Postulante $postulante, float $scoreTotal, array $breakdown): array
    {
        $flags = [];
        $reasons = [];

        // Identify traditional flags for compatibility
        if ($scoreTotal >= 50.0 && $scoreTotal <= 65.0) {
            $flags[] = 'score_gray_zone';
            $reasons[] = "Puntaje total ({$scoreTotal} pts) situado en la zona gris/ambigua de selección (50-65 pts).";
        }

        $expSummary = $postulante->experienceSummary;
        if ($expSummary) {
            $estOverlap = (int) $expSummary->overlap_months_estimated;
            $accum5 = (int) $expSummary->accumulated_months_last_5_years;

            if ($estOverlap >= 24) {
                $flags[] = 'severe_overlap_detected';
                $reasons[] = "Solapamiento laboral severo detectado: {$estOverlap} meses de empleos paralelos.";
            }
            if ($accum5 >= 90) {
                $flags[] = 'severe_overlap_detected';
                $reasons[] = "Intensidad laboral paralela extrema: {$accum5} meses registrados en los últimos 5 años.";
            }
        }

        $normalizationConfidences = [];
        foreach ($postulante->formacionesAcademicas as $f) {
            if ($f->normalization_confidence !== null) {
                $normalizationConfidences[] = (float) $f->normalization_confidence;
            }
        }
        foreach ($postulante->experienciasProfesionales as $p) {
            if ($p->normalization_confidence !== null) {
                $normalizationConfidences[] = (float) $p->normalization_confidence;
            }
        }

        $avgConfidence = count($normalizationConfidences) > 0 
            ? array_sum($normalizationConfidences) / count($normalizationConfidences) 
            : 100.00;

        if ($avgConfidence < 70.0) {
            $flags[] = 'low_normalization_confidence';
            $reasons[] = "Baja confianza promedio de normalización semántica (" . round($avgConfidence, 1) . "%).";
        }

        $highestFormacion = null;
        $maxHierarchy = -1;
        foreach ($postulante->formacionesAcademicas as $f) {
            $level = $f->academicLevel;
            if ($level && $level->hierarchy_order > $maxHierarchy) {
                $maxHierarchy = $level->hierarchy_order;
                $highestFormacion = $f;
            }
        }

        if ($highestFormacion) {
            $academicWeight = (float) ($breakdown['academic_formation']['max_points'] ?? 0.0);
            $hasCareer = !empty($highestFormacion->career_id);
            $hasArea = !empty($highestFormacion->professional_area_id);
            
            $triggerUncertain = false;
            if (!$hasCareer && !$hasArea) {
                $triggerUncertain = true;
                $reasons[] = "Carrera e Ingeniería sin área ni carrera reconocida: '{$highestFormacion->carrera_raw}'.";
            } elseif (!$hasCareer && $academicWeight >= 20.0) {
                $triggerUncertain = true;
                $reasons[] = "Vacante con alto peso académico exige validación de título exacto para '{$highestFormacion->carrera_raw}'.";
            }

            if ($triggerUncertain) {
                $flags[] = 'academic_match_uncertain';
            }
        }

        // Invoke the Risk Stratification Engine (Fase 2 y 3)
        $riskService = new \App\Services\Evaluation\Risk\ReviewRiskScoringService();
        $riskResult = $riskService->calculate($postulante, $scoreTotal, $breakdown);

        $riskScore = $riskResult['risk_score'];
        $riskLevel = $riskResult['risk_level'];
        $riskReasons = $riskResult['risk_reasons'];

        // Core business rule: Human Review is ONLY required if risk is high or critical (Fase 5)
        $requiresHuman = in_array($riskLevel, ['high', 'critical']);

        // Evaluation Status Rules (Fase 4)
        $evaluationStatus = 'evaluated';
        if ($riskLevel === 'low' && $scoreTotal >= 80.0 && $avgConfidence >= 85.0) {
            $evaluationStatus = 'auto_approved';
        } elseif ($requiresHuman) {
            $evaluationStatus = 'requires_human_review';
        }

        // Summary sentence prioritizing high risk reasons
        $summary = count($riskReasons) > 0 
            ? implode(' ', $riskReasons) 
            : "Evaluación completada conforme. Cumple con todos los parámetros de negocio.";

        return [
            'requires_human_review' => $requiresHuman,
            'review_risk_score'     => $riskScore,
            'review_risk_level'     => $riskLevel,
            'review_flags'          => $flags,
            'review_reason_summary' => $summary,
            'evaluation_status'     => $evaluationStatus,
        ];
    }
}
