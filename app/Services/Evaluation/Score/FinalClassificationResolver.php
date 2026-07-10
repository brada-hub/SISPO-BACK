<?php

namespace App\Services\Evaluation\Score;

class FinalClassificationResolver
{
    /**
     * Resolve classification based on final cumulative score.
     */
    public function resolve(float $scoreTotal): string
    {
        if ($scoreTotal >= 80.0) {
            return 'apto';
        }
        
        if ($scoreTotal >= 55.0) {
            return 'parcialmente_apto';
        }

        return 'no_apto';
    }
}
