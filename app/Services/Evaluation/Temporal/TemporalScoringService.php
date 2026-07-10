<?php

namespace App\Services\Evaluation\Temporal;

use App\Models\ExperienciaProfesional;
use App\Models\ExperienciaDocencia;
use Carbon\Carbon;

class TemporalScoringService
{
    protected Carbon $referenceDate;

    public function __construct(
        protected ExperienceRecencyCalculator $profCalculator,
        protected TeachingRecencyCalculator $docenciaCalculator
    ) {
        // Set standard reference date: 2026-05-17
        $this->referenceDate = Carbon::create(2026, 5, 17, 12, 0, 0);
    }

    /**
     * Get or set custom reference date.
     */
    public function getReferenceDate(): Carbon
    {
        return $this->referenceDate->copy();
    }

    public function setReferenceDate(Carbon $date): self
    {
        $this->referenceDate = $date;
        return $this;
    }

    /**
     * Score a single professional experience.
     *
     * @param ExperienciaProfesional $record
     * @return array [ 'is_current' => bool, 'last_experience_date' => Carbon, 'months_total' => int, 'months_in_last_3_years' => int, 'months_in_last_5_years' => int, 'recency_score' => float ]
     */
    public function scoreProfessionalExperience(ExperienciaProfesional $record): array
    {
        $start = Carbon::parse($record->fecha_inicio);
        $end = $record->fecha_fin ? Carbon::parse($record->fecha_fin) : null;

        $isCurrent = $this->profCalculator->detectCurrentExperience($end, $this->referenceDate);
        $lastDate = $end ?? $this->referenceDate->copy();

        $monthsTotal = $this->profCalculator->calculateMonthsTotal($start, $end);
        $months3Years = $this->profCalculator->calculateMonthsLast3Years($start, $end, $this->referenceDate);
        $months5Years = $this->profCalculator->calculateMonthsLast5Years($start, $end, $this->referenceDate);
        $recencyScore = $this->profCalculator->calculateRecencyScore($start, $end, $this->referenceDate);

        return [
            'is_current'             => $isCurrent,
            'last_experience_date'   => $lastDate,
            'months_total'           => $monthsTotal,
            'months_in_last_3_years' => $months3Years,
            'months_in_last_5_years' => $months5Years,
            'recency_score'          => $recencyScore,
        ];
    }

    /**
     * Score a single teaching experience.
     *
     * @param ExperienciaDocencia $record
     * @return array|null [ 'teaching_year_start' => int, 'teaching_year_end' => int, 'years_in_last_5_years' => int, 'is_recent_last_5_years' => bool, 'last_teaching_year' => int, 'recency_score' => float ]
     */
    public function scoreTeachingExperience(ExperienciaDocencia $record): ?array
    {
        $parsed = $this->docenciaCalculator->parseTeachingPeriods($record->gestion_periodo);

        if (!$parsed) {
            return null; // Invalid period format
        }

        $startYear = $parsed['start'];
        $endYear = $parsed['end'];
        $refYear = $this->referenceDate->year;

        $years5Years = $this->docenciaCalculator->calculateTeachingYears($startYear, $endYear, $refYear);
        $isRecent = $this->docenciaCalculator->detectRecentTeaching($endYear, $refYear);
        $recencyScore = $this->docenciaCalculator->calculateTeachingRecencyScore($endYear, $refYear);

        return [
            'teaching_year_start'    => $startYear,
            'teaching_year_end'      => $endYear,
            'years_in_last_5_years'  => $years5Years,
            'is_recent_last_5_years' => $isRecent,
            'last_teaching_year'     => $endYear,
            'recency_score'          => $recencyScore,
        ];
    }
}
