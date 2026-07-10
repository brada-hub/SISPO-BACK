<?php

namespace App\Services\Normalization;

class SemanticMatcher
{
    /**
     * Perform deterministic matching on a catalog collection.
     *
     * Each item in collection should have:
     * - id
     * - canonical_name
     * - aliases_json (cast as array)
     *
     * @param string|null $rawInput
     * @param \Illuminate\Support\Collection $catalog
     * @param float $minFuzzyThreshold
     * @return array|null [ 'id' => int, 'confidence' => float, 'method' => string, 'canonical_name' => string ]
     */
    public static function match(?string $rawInput, $catalog, float $minFuzzyThreshold = 0.65): ?array
    {
        if (empty($rawInput)) {
            return null;
        }

        $cleanInput = TextNormalizer::clean($rawInput);
        if (empty($cleanInput)) {
            return null;
        }

        // 1. EXACT MATCH on canonical name
        foreach ($catalog as $item) {
            $cleanCanonical = TextNormalizer::clean($item->canonical_name);
            if ($cleanInput === $cleanCanonical) {
                return [
                    'id'             => $item->id,
                    'canonical_name' => $item->canonical_name,
                    'confidence'     => 1.00,
                    'method'         => 'exact',
                ];
            }
        }

        // 2. ALIAS MATCH
        foreach ($catalog as $item) {
            $aliases = $item->aliases_json ?? [];
            if (is_array($aliases)) {
                foreach ($aliases as $alias) {
                    if ($cleanInput === TextNormalizer::clean($alias)) {
                        return [
                            'id'             => $item->id,
                            'canonical_name' => $item->canonical_name,
                            'confidence'     => 1.00,
                            'method'         => 'alias',
                        ];
                    }
                }
            }
        }

        // 3. FUZZY MATCH (Levenshtein + Keyword Similarity)
        $bestMatch = null;
        $bestScore = 0.0;

        foreach ($catalog as $item) {
            // Check canonical
            $simCanonical = TextNormalizer::similarity($cleanInput, $item->canonical_name);
            $kwCanonical = TextNormalizer::keywordSimilarity($cleanInput, $item->canonical_name);
            
            // Weight: 40% Levenshtein, 60% Keyword overlap
            $score = (0.4 * $simCanonical) + (0.6 * $kwCanonical);

            // Check aliases
            $aliases = $item->aliases_json ?? [];
            if (is_array($aliases)) {
                foreach ($aliases as $alias) {
                    $simAlias = TextNormalizer::similarity($cleanInput, $alias);
                    $kwAlias = TextNormalizer::keywordSimilarity($cleanInput, $alias);
                    $aliasScore = (0.4 * $simAlias) + (0.6 * $kwAlias);
                    
                    if ($aliasScore > $score) {
                        $score = $aliasScore;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $item;
            }
        }

        if ($bestMatch && $bestScore >= $minFuzzyThreshold) {
            return [
                'id'             => $bestMatch->id,
                'canonical_name' => $bestMatch->canonical_name,
                'confidence'     => round($bestScore, 2),
                'method'         => 'fuzzy',
            ];
        }

        // No match found
        return null;
    }
}
