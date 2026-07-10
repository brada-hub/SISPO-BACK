<?php

namespace App\Services\Normalization;

use App\Models\CatalogCareer;

class CareerNormalizer
{
    /**
     * Match a raw career name against catalog_careers.
     *
     * @param string|null $rawInput
     * @return array|null [ 'id' => int, 'confidence' => float, 'method' => string, 'professional_area_id' => int|null ]
     */
    public function matchCareer(?string $rawInput): ?array
    {
        if (empty($rawInput)) {
            return null;
        }

        $catalog = CatalogCareer::where('is_active', true)->get();
        
        $match = SemanticMatcher::match($rawInput, $catalog, 0.65);

        if ($match) {
            $career = CatalogCareer::find($match['id']);
            return [
                'id'                   => $match['id'],
                'confidence'           => $match['confidence'],
                'method'               => $match['method'],
                'professional_area_id' => $career?->professional_area_id,
            ];
        }

        return null;
    }
}
