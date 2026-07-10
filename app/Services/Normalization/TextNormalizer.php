<?php

namespace App\Services\Normalization;

class TextNormalizer
{
    /**
     * Remove accents and diacritics from string.
     */
    public static function removeAccents(?string $str): string
    {
        if (empty($str)) {
            return '';
        }

        $unwanted = [
            'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A',
            'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O',
            'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B',
            'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
            'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o',
            'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
        ];

        return strtr($str, $unwanted);
    }

    /**
     * Lowercase, strip accents, replace tabs/newlines with spaces, trim.
     */
    public static function clean(?string $str): string
    {
        if (empty($str)) {
            return '';
        }

        $str = self::removeAccents($str);
        $str = mb_strtolower($str, 'UTF-8');
        
        // Remove special punctuation, replace with space
        $str = preg_replace('/[.,\/#!$%\^&\*;:{}=\-_`~()?"\']/u', ' ', $str);
        
        // Normalize multiple spaces into one
        $str = preg_replace('/\s+/', ' ', $str);

        return trim($str);
    }

    /**
     * Generate standard slug from string.
     */
    public static function slugify(?string $str): string
    {
        if (empty($str)) {
            return '';
        }

        return \Illuminate\Support\Str::slug(self::removeAccents($str));
    }

    /**
     * Calculate normalized Levenshtein distance similarity [0.0 - 1.0].
     */
    public static function similarity(?string $str1, ?string $str2): float
    {
        $clean1 = self::clean($str1);
        $clean2 = self::clean($str2);

        if ($clean1 === $clean2) {
            return 1.0;
        }

        if (empty($clean1) || empty($clean2)) {
            return 0.0;
        }

        $lev = levenshtein($clean1, $clean2);
        $maxLen = max(strlen($clean1), strlen($clean2));

        if ($maxLen === 0) {
            return 1.0;
        }

        return 1.0 - ($lev / $maxLen);
    }

    /**
     * Calculate keyword intersection similarity [0.0 - 1.0].
     */
    public static function keywordSimilarity(?string $str1, ?string $str2): float
    {
        $clean1 = self::clean($str1);
        $clean2 = self::clean($str2);

        $words1 = array_filter(explode(' ', $clean1), fn($w) => strlen($w) > 2);
        $words2 = array_filter(explode(' ', $clean2), fn($w) => strlen($w) > 2);

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        $intersection = array_intersect($words1, $words2);
        $minCount = min(count($words1), count($words2));

        if ($minCount === 0) {
            return 0.0;
        }

        return count($intersection) / $minCount;
    }
}
