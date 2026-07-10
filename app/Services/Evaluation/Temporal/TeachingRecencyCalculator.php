<?php

namespace App\Services\Evaluation\Temporal;

use Carbon\Carbon;

class TeachingRecencyCalculator
{
    /**
     * Parse raw teaching period string to extract start and end years.
     *
     * @param string|null $period
     * @return array|null [ 'start' => int, 'end' => int ]
     */
    public function parseTeachingPeriods(?string $period): ?array
    {
        if (empty($period)) {
            return null;
        }

        // Clean spaces and standardize dividers
        $cleaned = trim(preg_replace('/\s+/', ' ', str_replace(['/', 'a', 'al', 'to', 'and', 'y'], '-', $period)));

        // Find all 4-digit numbers (years)
        if (preg_match_all('/\b(19\d{2}|20\d{2})\b/', $cleaned, $matches)) {
            $years = array_map('intval', $matches[0]);

            if (count($years) === 1) {
                return ['start' => $years[0], 'end' => $years[0]];
            }

            if (count($years) >= 2) {
                sort($years);
                return ['start' => $years[0], 'end' => end($years)];
            }
        }

        // Fallback for single 2-digit years like "22" or "24" in context like "I/24"
        if (preg_match('/\b(\d{2})\b/', $cleaned, $match)) {
            $shortYear = (int) $match[1];
            $fullYear = $shortYear < 50 ? 2000 + $shortYear : 1900 + $shortYear;
            return ['start' => $fullYear, 'end' => $fullYear];
        }

        return null;
    }

    /**
     * Calculate how many teaching years fall within the last 5 years window (2021-2026).
     */
    public function calculateTeachingYears(int $start, int $end, int $referenceYear): int
    {
        $windowStart = $referenceYear - 5;
        
        if ($end < $windowStart || $start > $referenceYear) {
            return 0;
        }

        $overlapStart = max($start, $windowStart);
        $overlapEnd = min($end, $referenceYear);

        return max(1, ($overlapEnd - $overlapStart) + 1);
    }

    /**
     * Determine if the teaching took place in the last 5 years.
     */
    public function detectRecentTeaching(int $endYear, int $referenceYear): bool
    {
        return $endYear >= ($referenceYear - 5);
    }

    /**
     * Calculate teaching recency score based on difference between last teaching year and reference year.
     */
    public function calculateTeachingRecencyScore(int $endYear, int $referenceYear): float
    {
        $diffYears = $referenceYear - $endYear;

        if ($diffYears <= 0) {
            return 100.00; // Active this year or future
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
