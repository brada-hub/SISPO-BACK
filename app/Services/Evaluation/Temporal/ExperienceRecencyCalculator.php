<?php

namespace App\Services\Evaluation\Temporal;

use Carbon\Carbon;

class ExperienceRecencyCalculator
{
    /**
     * Calculate total duration of experience in months.
     */
    public function calculateMonthsTotal(Carbon $start, ?Carbon $end): int
    {
        $end = $end ?? Carbon::now();
        if ($start->greaterThan($end)) {
            return 0;
        }
        return (int) max(1, round($start->diffInMonths($end)));
    }

    /**
     * Calculate overlap duration in months between the experience and a reference interval.
     */
    public function calculateOverlapMonths(Carbon $start, ?Carbon $end, Carbon $windowStart, Carbon $windowEnd): int
    {
        $end = $end ?? Carbon::now();

        // No overlap cases
        if ($end->lessThan($windowStart) || $start->greaterThan($windowEnd)) {
            return 0;
        }

        // Calculate overlap boundaries
        $overlapStart = $start->greaterThan($windowStart) ? $start : $windowStart;
        $overlapEnd = $end->lessThan($windowEnd) ? $end : $windowEnd;

        if ($overlapStart->greaterThan($overlapEnd)) {
            return 0;
        }

        return (int) max(0, round($overlapStart->diffInMonths($overlapEnd)));
    }

    /**
     * Calculate months of experience in the last 3 years (reference date is 2026-05-17).
     */
    public function calculateMonthsLast3Years(Carbon $start, ?Carbon $end, Carbon $referenceDate): int
    {
        $windowStart = $referenceDate->copy()->subYears(3);
        return $this->calculateOverlapMonths($start, $end, $windowStart, $referenceDate);
    }

    /**
     * Calculate months of experience in the last 5 years.
     */
    public function calculateMonthsLast5Years(Carbon $start, ?Carbon $end, Carbon $referenceDate): int
    {
        $windowStart = $referenceDate->copy()->subYears(5);
        return $this->calculateOverlapMonths($start, $end, $windowStart, $referenceDate);
    }

    /**
     * Determine if the experience is current.
     */
    public function detectCurrentExperience(?Carbon $end, Carbon $referenceDate): bool
    {
        if ($end === null) {
            return true;
        }
        // If end date is in the future or in the current month/year
        return $end->greaterThanOrEqualTo($referenceDate->copy()->startOfMonth());
    }

    /**
     * Calculate recency score based on ending year compared to reference year (2026).
     */
    public function calculateRecencyScore(Carbon $start, ?Carbon $end, Carbon $referenceDate): float
    {
        $isCurrent = $this->detectCurrentExperience($end, $referenceDate);
        $lastDate = $end ?? $referenceDate;

        if ($isCurrent) {
            return 100.00;
        }

        $refYear = $referenceDate->year;
        $endYear = $lastDate->year;
        $diffYears = $refYear - $endYear;

        if ($diffYears <= 0) {
            return 100.00; // Ended in current year (2026)
        }

        switch ($diffYears) {
            case 1:
                return 90.00;  // Ended in 2025
            case 2:
                return 80.00;  // Ended in 2024
            case 3:
                return 70.00;  // Ended in 2023
            case 4:
            case 5:
                return 50.00;  // Ended in 2021-2022
            case 6:
            case 7:
            case 8:
            case 9:
            case 10:
                return 30.00;  // Ended in 2016-2020
            default:
                return 10.00;  // Ended before 2016
        }
    }
}
