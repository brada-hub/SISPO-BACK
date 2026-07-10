<?php

namespace App\Services\Normalization;

use App\Models\CatalogJobPosition;

class JobPositionNormalizer
{
    /**
     * Match a raw job position against catalog_job_positions.
     *
     * @param string|null $rawInput
     * @return array|null [ 'id' => int, 'confidence' => float, 'method' => string, 'seniority_level' => string|null ]
     */
    public function matchJobPosition(?string $rawInput): ?array
    {
        if (empty($rawInput)) {
            return null;
        }

        $catalog = CatalogJobPosition::where('is_active', true)->get();
        
        $match = SemanticMatcher::match($rawInput, $catalog, 0.65);

        if ($match) {
            $position = CatalogJobPosition::find($match['id']);
            return [
                'id'                   => $match['id'],
                'confidence'           => $match['confidence'],
                'method'               => $match['method'],
                'seniority_level'      => $position?->seniority_level,
            ];
        }

        return null;
    }
}
