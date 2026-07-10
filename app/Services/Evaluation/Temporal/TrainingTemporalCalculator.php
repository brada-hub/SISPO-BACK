<?php

namespace App\Services\Evaluation\Temporal;

use App\Models\Postulante;
use App\Models\Capacitacion;
use App\Models\PostulanteTrainingSummary;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TrainingTemporalCalculator
{
    /**
     * Anchor date for recency calculations (strictly 2026-05-17)
     */
    protected const ANCHOR_DATE = '2026-05-17';

    /**
     * Process temporal and recency metrics for a candidate's trainings.
     */
    public function process(int $postulanteId, bool $write = false): array
    {
        $postulante = Postulante::with('capacitaciones')->findOrFail($postulanteId);
        $capacitaciones = $postulante->capacitaciones;

        $anchor = Carbon::parse(self::ANCHOR_DATE);
        $anchorYear = $anchor->year; // 2026

        $totalRecords = $capacitaciones->count();
        $totalHours = 0;
        $hoursLast3Years = 0;
        $hoursLast5Years = 0;
        $lastTrainingDate = null;
        
        $recencyScores = [];
        $invalidDatesCount = 0;

        foreach ($capacitaciones as $cap) {
            $date = null;

            // Attempt to parse date safely
            if ($cap->fecha) {
                try {
                    $date = Carbon::parse($cap->fecha);
                } catch (\Throwable $e) {
                    $invalidDatesCount++;
                }
            }

            if (!$date) {
                // Try parsing from raw respuestas JSON if possible
                if ($cap->sourceMerito && $cap->sourceMerito->respuestas) {
                    $respuestas = $cap->sourceMerito->respuestas;
                    $rawDate = $respuestas['fecha'] ?? null;
                    if ($rawDate) {
                        try {
                            $date = Carbon::parse($rawDate);
                        } catch (\Throwable $e) {
                            $invalidDatesCount++;
                        }
                    }
                }
            }

            // Fallback for completely invalid/null dates (assign old fallback year)
            if (!$date) {
                $date = Carbon::parse('2010-01-01');
                $invalidDatesCount++;
            }

            $year = $date->year;
            $diffYears = max(0, $anchorYear - $year);

            // Compute recency score based on year offsets
            $recencyScore = 10.0;
            if ($diffYears == 0) {
                $recencyScore = 100.0;
            } elseif ($diffYears == 1) {
                $recencyScore = 90.0;
            } elseif ($diffYears == 2) {
                $recencyScore = 80.0;
            } elseif ($diffYears == 3) {
                $recencyScore = 70.0;
            } elseif ($diffYears >= 4 && $diffYears <= 5) {
                $recencyScore = 50.0;
            } elseif ($diffYears >= 6 && $diffYears <= 10) {
                $recencyScore = 30.0;
            } else {
                $recencyScore = 10.0;
            }

            $recencyScores[] = $recencyScore;

            // Time windows logic (anchored to 2026-05-17)
            // Last 3 years: since 2023-05-17
            // Last 5 years: since 2021-05-17
            $threeYearsAgo = (clone $anchor)->subYears(3);
            $fiveYearsAgo = (clone $anchor)->subYears(5);

            $isRecent3 = $date->greaterThanOrEqualTo($threeYearsAgo);
            $isRecent5 = $date->greaterThanOrEqualTo($fiveYearsAgo);

            $hours = (int) $cap->carga_horaria;
            $totalHours += $hours;

            if ($isRecent3) {
                $hoursLast3Years += $hours;
            }
            if ($isRecent5) {
                $hoursLast5Years += $hours;
            }

            // Track latest date
            if ($lastTrainingDate === null || $date->greaterThan($lastTrainingDate)) {
                $lastTrainingDate = $date;
            }

            if ($write) {
                $cap->update([
                    'training_year'          => $year,
                    'last_training_date'     => $date,
                    'is_recent_last_3_years' => $isRecent3,
                    'is_recent_last_5_years' => $isRecent5,
                    'temporal_weight'        => $recencyScore / 100.0,
                    'training_recency_score' => $recencyScore,
                    'temporal_processed_at'  => Carbon::now(),
                ]);
            }
        }

        // Summary aggregated metrics
        $maxRecency = count($recencyScores) > 0 ? max($recencyScores) : 0.0;
        $avgRecency = count($recencyScores) > 0 ? (array_sum($recencyScores) / count($recencyScores)) : 0.0;

        $resultPayload = [
            'postulante_id'                  => $postulanteId,
            'total_training_records'         => $totalRecords,
            'total_training_hours'           => $totalHours,
            'training_hours_last_3_years'    => $hoursLast3Years,
            'training_hours_last_5_years'    => $hoursLast5Years,
            'last_training_date'             => $lastTrainingDate ? $lastTrainingDate->format('Y-m-d') : null,
            'max_training_recency_score'     => round($maxRecency, 2),
            'average_training_recency_score' => round($avgRecency, 2),
            'processed_at'                   => Carbon::now()->toDateTimeString(),
            'invalid_dates_count'            => $invalidDatesCount,
        ];

        if ($write) {
            PostulanteTrainingSummary::updateOrCreate(
                ['postulante_id' => $postulanteId],
                array_merge($resultPayload, ['processed_at' => Carbon::now()])
            );
        }

        return $resultPayload;
    }
}
