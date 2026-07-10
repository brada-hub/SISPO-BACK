<?php

namespace App\Services\Normalization;

use App\Models\CatalogAcademicLevel;

class AcademicLevelNormalizer
{
    /**
     * Normalize raw academic level string to catalog_academic_levels entry.
     *
     * @param string|null $rawInput
     * @return array|null [ 'id' => int, 'confidence' => float, 'method' => string ]
     */
    public function normalizeAcademicLevel(?string $rawInput): ?array
    {
        if (empty($rawInput)) {
            return null;
        }

        $catalog = CatalogAcademicLevel::all();
        
        // Match exact or alias
        $match = SemanticMatcher::match($rawInput, $catalog, 0.60);

        if ($match) {
            return [
                'id'         => $match['id'],
                'confidence' => $match['confidence'],
                'method'     => $match['method'],
            ];
        }

        return null;
    }
}
