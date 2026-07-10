<?php

namespace App\Services\Evaluation\Temporal;

use App\Models\Postulante;
use App\Models\ExperienciaProfesional;
use Carbon\Carbon;

class ExperienceOverlapCalculator
{
    protected Carbon $referenceDate;

    public function __construct()
    {
        // Reference date: 2026-05-17
        $this->referenceDate = Carbon::create(2026, 5, 17, 12, 0, 0);
    }

    /**
     * Set a custom reference date.
     */
    public function setReferenceDate(Carbon $date): self
    {
        $this->referenceDate = $date;
        return $this;
    }

    /**
     * Build intervals [start, end] from a collection of experiences.
     *
     * @param \Illuminate\Support\Collection|array $experiences
     * @return array Array of ['start' => Carbon, 'end' => Carbon]
     */
    public function buildIntervals($experiences): array
    {
        $intervals = [];

        foreach ($experiences as $exp) {
            if (empty($exp->fecha_inicio)) {
                continue;
            }

            $start = Carbon::parse($exp->fecha_inicio);
            
            // If fecha_fin is null and it's marked as current, use the reference date
            $end = $exp->fecha_fin 
                ? Carbon::parse($exp->fecha_fin) 
                : ($exp->is_current ? $this->referenceDate->copy() : $start->copy());

            if ($start->greaterThan($end)) {
                continue; // Skip invalid intervals
            }

            $intervals[] = [
                'start' => $start,
                'end' => $end,
            ];
        }

        return $intervals;
    }

    /**
     * Clip intervals to fall strictly within a window [windowStart, windowEnd].
     */
    public function clipIntervalsToWindow(array $intervals, Carbon $windowStart, Carbon $windowEnd): array
    {
        $clipped = [];

        foreach ($intervals as $interval) {
            $start = $interval['start'];
            $end = $interval['end'];

            // No overlap cases
            if ($end->lessThan($windowStart) || $start->greaterThan($windowEnd)) {
                continue;
            }

            $overlapStart = $start->greaterThan($windowStart) ? $start->copy() : $windowStart->copy();
            $overlapEnd = $end->lessThan($windowEnd) ? $end->copy() : $windowEnd->copy();

            if ($overlapStart->greaterThan($overlapEnd)) {
                continue;
            }

            $clipped[] = [
                'start' => $overlapStart,
                'end' => $overlapEnd,
            ];
        }

        return $clipped;
    }

    /**
     * Merge overlapping intervals.
     *
     * @param array $intervals
     * @return array Merged non-overlapping intervals sorted by start date.
     */
    public function mergeOverlappingIntervals(array $intervals): array
    {
        if (empty($intervals)) {
            return [];
        }

        // Sort intervals by start date
        usort($intervals, function ($a, $b) {
            return $a['start']->timestamp <=> $b['start']->timestamp;
        });

        $merged = [];
        $current = $intervals[0];

        for ($i = 1; $i < count($intervals); $i++) {
            $next = $intervals[$i];

            // If the current interval overlaps or is contiguous with the next one
            if ($current['end']->greaterThanOrEqualTo($next['start'])) {
                // Merge them by extending the end date of the current one if next is greater
                if ($next['end']->greaterThan($current['end'])) {
                    $current['end'] = $next['end'];
                }
            } else {
                $merged[] = $current;
                $current = $next;
            }
        }

        $merged[] = $current;

        return $merged;
    }

    /**
     * Calculate unique months from non-overlapping intervals.
     */
    public function calculateMonthsFromIntervals(array $intervals): int
    {
        $totalMonths = 0;

        foreach ($intervals as $interval) {
            $start = $interval['start'];
            $end = $interval['end'];

            $diff = $start->diffInMonths($end);
            
            // At least 1 month if there's any active period
            $totalMonths += (int) max(1, round($diff));
        }

        return $totalMonths;
    }

    /**
     * Summarize all experience metrics for a given Postulante.
     *
     * @param int $postulanteId
     * @return array|null Summary data ready to persist
     */
    public function summarizeForPostulante(int $postulanteId): ?array
    {
        $postulante = Postulante::with('experienciasProfesionales')->find($postulanteId);
        
        if (!$postulante) {
            return null;
        }

        $experiences = $postulante->experienciasProfesionales;
        $recordsCount = $experiences->count();

        if ($recordsCount === 0) {
            return [
                'postulante_id'                   => $postulanteId,
                'total_accumulated_months'        => 0,
                'total_unique_months'             => 0,
                'accumulated_months_last_3_years' => 0,
                'unique_months_last_3_years'      => 0,
                'accumulated_months_last_5_years' => 0,
                'unique_months_last_5_years'      => 0,
                'max_recency_score'               => null,
                'current_experience_count'        => 0,
                'experience_records_count'        => 0,
                'overlap_detected'                => false,
                'overlap_months_estimated'        => 0,
            ];
        }

        // Build base intervals
        $baseIntervals = $this->buildIntervals($experiences);

        // 1. Total Months Calculations
        $mergedTotal = $this->mergeOverlappingIntervals($baseIntervals);
        $totalUnique = $this->calculateMonthsFromIntervals($mergedTotal);
        $totalAccumulated = 0;
        foreach ($experiences as $exp) {
            $totalAccumulated += $exp->months_total ?? 0;
        }

        // 2. Windows definitions
        $refDate = $this->referenceDate;
        $window3 = $refDate->copy()->subYears(3);
        $window5 = $refDate->copy()->subYears(5);

        // 3. Last 3 Years Calculations
        $clipped3 = $this->clipIntervalsToWindow($baseIntervals, $window3, $refDate);
        $merged3 = $this->mergeOverlappingIntervals($clipped3);
        $unique3 = $this->calculateMonthsFromIntervals($merged3);
        $accumulated3 = 0;
        foreach ($experiences as $exp) {
            $accumulated3 += $exp->months_in_last_3_years ?? 0;
        }

        // Apply business cap: unique months in last 3 years cannot exceed 36
        $unique3 = min(36, $unique3);

        // 4. Last 5 Years Calculations
        $clipped5 = $this->clipIntervalsToWindow($baseIntervals, $window5, $refDate);
        $merged5 = $this->mergeOverlappingIntervals($clipped5);
        $unique5 = $this->calculateMonthsFromIntervals($merged5);
        $accumulated5 = 0;
        foreach ($experiences as $exp) {
            $accumulated5 += $exp->months_in_last_5_years ?? 0;
        }

        // Apply business cap: unique months in last 5 years cannot exceed 60
        $unique5 = min(60, $unique5);

        // 5. Metadatas
        $maxRecency = $experiences->max('recency_score');
        $currentCount = $experiences->where('is_current', true)->count();

        // Overlap detection
        $overlapEstimated = max(0, $totalAccumulated - $totalUnique);
        $overlapDetected = $overlapEstimated > 0 || $accumulated5 > $unique5;

        return [
            'postulante_id'                   => $postulanteId,
            'total_accumulated_months'        => $totalAccumulated,
            'total_unique_months'             => $totalUnique,
            'accumulated_months_last_3_years' => $accumulated3,
            'unique_months_last_3_years'      => $unique3,
            'accumulated_months_last_5_years' => $accumulated5,
            'unique_months_last_5_years'      => $unique5,
            'max_recency_score'               => $maxRecency,
            'current_experience_count'        => $currentCount,
            'experience_records_count'        => $recordsCount,
            'overlap_detected'                => $overlapDetected,
            'overlap_months_estimated'        => $overlapEstimated,
        ];
    }
}
