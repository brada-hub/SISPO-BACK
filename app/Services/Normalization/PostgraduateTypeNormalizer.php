<?php

namespace App\Services\Normalization;

use App\Models\CatalogPostgraduateType;

class PostgraduateTypeNormalizer
{
    /**
     * Normalize postgraduate type.
     *
     * @param string|null $rawInput
     * @return array|null [ 'id' => int, 'confidence' => float, 'method' => string ]
     */
    public function normalizePostgraduateType(?string $rawInput): ?array
    {
        if (empty($rawInput)) {
            return null;
        }

        $catalog = CatalogPostgraduateType::all();
        
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
