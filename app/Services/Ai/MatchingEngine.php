<?php

namespace App\Services\Ai;

use App\Models\AiCvAnalysis;
use App\Models\AiMatchingResult;
use App\Models\Convocatoria;
use App\Models\TipoDocumento;

/**
 * Deterministic matching engine.
 *
 * Combines AI-extracted profile data with rule-based scoring
 * to produce a transparent, auditable compatibility score.
 *
 * The AI provides the "what" (extracted data), this engine provides the "how much" (score).
 */
class MatchingEngine
{
    /**
     * Default scoring weights (can be overridden per convocatoria).
     */
    public const DEFAULT_WEIGHTS = [
        'formacion'   => 0.30,
        'experiencia' => 0.35,
        'habilidades' => 0.20,
        'requisitos'  => 0.15,
    ];

    /**
     * Academic level hierarchy for scoring.
     */
    private const LEVEL_SCORES = [
        'No detectado'      => 0,
        'Otro'              => 10,
        'Técnico Superior'  => 40,
        'Licenciatura'      => 70,
        'Maestría'          => 90,
        'Doctorado'         => 100,
    ];

    /**
     * Calculate the full matching result for a postulación.
     *
     * @param  AiCvAnalysis  $analysis       The completed CV analysis
     * @param  Convocatoria  $convocatoria   The target job posting
     * @param  array|null    $aiMatchingData Optional AI-generated matching evaluation
     * @param  array         $weights        Optional custom weights
     * @return array  The complete matching data ready for storage
     */
    public function calculate(
        AiCvAnalysis $analysis,
        Convocatoria $convocatoria,
        ?array $aiMatchingData = null,
        array $weights = []
    ): array {
        $weights = array_merge(self::DEFAULT_WEIGHTS, $weights);

        // 1. Score: Formación Académica
        $scoreFormacion = $this->scoreFormacion($analysis, $convocatoria);

        // 2. Score: Experiencia Laboral
        $scoreExperiencia = $this->scoreExperiencia($analysis, $convocatoria);

        // 3. Score: Habilidades (from AI matching data if available)
        $scoreHabilidades = $this->scoreHabilidades($analysis, $aiMatchingData);

        // 4. Score: Requisitos Documentales
        $scoreRequisitos = $this->scoreRequisitos($analysis, $convocatoria, $aiMatchingData);

        // 5. Weighted total
        $scoreTotal = round(
            ($scoreFormacion   * $weights['formacion'])
          + ($scoreExperiencia * $weights['experiencia'])
          + ($scoreHabilidades * $weights['habilidades'])
          + ($scoreRequisitos  * $weights['requisitos']),
            2
        );

        // Hard clamp score between 0 and 100
        $scoreTotal = max(0, min(100, $scoreTotal));

        // 6. Classification
        $clasificacion = AiMatchingResult::classify($scoreTotal);

        // Fallback: If AI extracted literally 0 experience, 0 formation, and 0 skills, it's a completely empty CV
        if ($scoreFormacion === 0.0 && $scoreExperiencia === 0.0 && $scoreHabilidades === 0.0) {
            $clasificacion = 'no_apto';
            $scoreTotal = 0;
            $aiMatchingData['observaciones'] = "El documento analizado no contiene datos de formación académica, experiencia laboral ni habilidades identificables. Se clasifica automáticamente como No Apto.";
        }

        // 7. Extract strengths/weaknesses from AI data
        $fortalezas  = $aiMatchingData['fortalezas'] ?? [];
        $debilidades = $aiMatchingData['debilidades'] ?? [];

        // 8. Build requirements detail
        $reqCumplidos = [];
        $reqFaltantes = [];

        if (isset($aiMatchingData['requisitos_evaluados'])) {
            foreach ($aiMatchingData['requisitos_evaluados'] as $req) {
                if ($req['cumple'] === true) {
                    $reqCumplidos[] = $req;
                } elseif ($req['cumple'] === 'parcial') {
                    $reqCumplidos[] = $req; // Count partial as met
                } else {
                    $reqFaltantes[] = $req;
                }
            }
        }

        return [
            'score_formacion'      => $scoreFormacion,
            'score_experiencia'    => $scoreExperiencia,
            'score_habilidades'    => $scoreHabilidades,
            'score_requisitos'     => $scoreRequisitos,
            'score_total'          => $scoreTotal,
            'pesos_aplicados'      => $weights,
            'clasificacion_ia'     => $clasificacion,
            'requisitos_cumplidos' => $reqCumplidos,
            'requisitos_faltantes' => $reqFaltantes,
            'observaciones_ia'     => $aiMatchingData['observaciones'] ?? null,
            'fortalezas'           => $fortalezas,
            'debilidades'          => $debilidades,
        ];
    }

    /**
     * Score academic formation (0-100).
     */
    private function scoreFormacion(AiCvAnalysis $analysis, Convocatoria $convocatoria): float
    {
        $detectedLevel = $analysis->nivel_academico ?? 'No detectado';
        $score = self::LEVEL_SCORES[$detectedLevel] ?? 10;

        // Bonus for number of academic entries
        $formacionCount = count($analysis->formacion_academica ?? []);
        $bonus = min($formacionCount * 5, 20); // max 20 bonus points

        return min($score + $bonus, 100.0);
    }

    /**
     * Score professional experience (0-100).
     */
    private function scoreExperiencia(AiCvAnalysis $analysis, Convocatoria $convocatoria): float
    {
        $years = (float) ($analysis->anios_experiencia ?? 0);

        // Logarithmic scale: diminishing returns after 10 years
        if ($years <= 0) {
            return 0;
        }

        if ($years >= 10) {
            return 100;
        }

        // Score = 100 * (years / 10), with a slight curve
        return round(min(100, ($years / 10) * 100), 2);
    }

    /**
     * Score technical skills match (0-100).
     */
    private function scoreHabilidades(AiCvAnalysis $analysis, ?array $aiMatchingData): float
    {
        $skills = $analysis->habilidades ?? [];

        if (empty($skills)) {
            return 20; // Baseline — can't score 0 just because extraction was incomplete
        }

        // If we have AI matching data with confidence scores, use those
        if (isset($aiMatchingData['requisitos_evaluados'])) {
            $totalConfidence = 0;
            $count = 0;

            foreach ($aiMatchingData['requisitos_evaluados'] as $req) {
                $totalConfidence += (float) ($req['confianza'] ?? 50);
                $count++;
            }

            if ($count > 0) {
                return round($totalConfidence / $count, 2);
            }
        }

        // Fallback: score based on number of skills detected
        $skillCount = count($skills);
        return min(100, $skillCount * 10);
    }

    /**
     * Score documentary requirements fulfillment (0-100).
     */
    private function scoreRequisitos(AiCvAnalysis $analysis, Convocatoria $convocatoria, ?array $aiMatchingData): float
    {
        if (! isset($aiMatchingData['requisitos_evaluados']) || empty($aiMatchingData['requisitos_evaluados'])) {
            return 50; // Can't evaluate without data — neutral score
        }

        $total   = count($aiMatchingData['requisitos_evaluados']);
        $met     = 0;
        $partial = 0;

        foreach ($aiMatchingData['requisitos_evaluados'] as $req) {
            if ($req['cumple'] === true) {
                $met++;
            } elseif ($req['cumple'] === 'parcial') {
                $partial++;
            }
        }

        if ($total === 0) {
            return 50;
        }

        // Full requirements count 100%, partial count 50%
        return round((($met + $partial * 0.5) / $total) * 100, 2);
    }
}
