<?php

namespace App\Services\Evaluation\Risk;

use App\Models\Postulante;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReviewRiskScoringService
{
    /**
     * Compute risk score (0-100) and risk level (low, medium, high, critical)
     * for a postulant's deterministic evaluation results.
     */
    public function calculate(Postulante $postulante, float $scoreTotal, array $breakdown): array
    {
        $riskScore = 0.0;
        $reasons = [];
        $hasCriticalFlag = false;

        // 1. Overlap Risk Factor
        $expSummary = $postulante->experienceSummary;
        if ($expSummary) {
            $estOverlap = (int) $expSummary->overlap_months_estimated;
            $accum5 = (int) $expSummary->accumulated_months_last_5_years;

            if ($estOverlap >= 36) {
                $riskScore += 50.0;
                $hasCriticalFlag = true;
                $reasons[] = "Solapamiento laboral ultra-severo de {$estOverlap} meses.";
            } elseif ($estOverlap >= 24) {
                $riskScore += 35.0;
                $reasons[] = "Solapamiento laboral severo de {$estOverlap} meses.";
            } elseif ($estOverlap >= 12) {
                $riskScore += 15.0;
                $reasons[] = "Solapamiento laboral moderado de {$estOverlap} meses.";
            }

            if ($accum5 >= 90) {
                $riskScore += 40.0;
                $hasCriticalFlag = true;
                $reasons[] = "Intensidad laboral paralela extrema de {$accum5} meses en los últimos 5 años.";
            }
        }

        // 2. Academic Career Normalization Risk
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
            $hasCareer = !empty($highestFormacion->career_id);
            $hasArea = !empty($highestFormacion->professional_area_id);
            $academicWeight = (float) ($breakdown['academic_formation']['max_points'] ?? 0.0);

            if (!$hasCareer && !$hasArea) {
                $riskScore += 35.0;
                $reasons[] = "Carrera académica sin correspondencia en catálogo ni áreas: '{$highestFormacion->carrera_raw}'.";
            } elseif (!$hasCareer && $academicWeight >= 20.0) {
                $riskScore += 25.0;
                $reasons[] = "Alto peso de convocatoria exige validación de título exacto para '{$highestFormacion->carrera_raw}'.";
            } elseif (!$hasCareer && $hasArea) {
                $riskScore += 8.0; // Moderate ambiguity
                $reasons[] = "Normalizado a nivel de Área Profesional pero sin coincidencia exacta de Carrera.";
            }
        }

        // 3. Date Consistency Risk (Fechas imposibles)
        foreach ($postulante->formacionesAcademicas as $f) {
            $date = $f->fecha_diploma ?: $f->fecha_titulo;
            if ($date) {
                $year = (int) $date->format('Y');
                if ($year < 1960 || $year > 2026) {
                    $riskScore += 45.0;
                    $hasCriticalFlag = true;
                    $reasons[] = "Fecha académica anómala o imposible detectada: Año {$year}.";
                }
            }
        }

        // 4. Low Normalization Semantic Confidence average
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
            $riskScore += 18.0;
            $reasons[] = "Baja confianza promedio de normalización semántica (" . round($avgConfidence, 1) . "%).";
        }

        // 5. Score Gray Zone Risk
        if ($scoreTotal >= 50.0 && $scoreTotal <= 65.0) {
            $riskScore += 15.0;
            $reasons[] = "Puntaje total ({$scoreTotal} pts) en zona gris de postulación.";
        }

        // Cap risk score between 0 and 100
        $riskScore = min(100.00, round($riskScore, 2));

        // Risk level Stratification (Fase 3)
        $riskLevel = 'low';
        if ($hasCriticalFlag || $riskScore >= 60.0) {
            $riskLevel = 'critical';
        } elseif ($riskScore >= 35.0) {
            $riskLevel = 'high';
        } elseif ($riskScore >= 12.0) {
            $riskLevel = 'medium';
        }

        Log::info("[RiskScoring] Postulante ID {$postulante->id}: score={$riskScore} level={$riskLevel}");

        return [
            'risk_score'   => $riskScore,
            'risk_level'   => $riskLevel,
            'risk_reasons' => $reasons,
        ];
    }
}
