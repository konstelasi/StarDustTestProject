<?php

namespace App\Support;

/**
 * Custom SKU algorithm — per team decision, SKU is the one field that must
 * NEVER come from Faker. It needs to be deterministic-looking, traceable to
 * category + warehouse + time, and self-verifiable (a real inventory system
 * usually wants a lightweight check digit to catch typos at receiving/scan
 * time), which a plain random string can't give you.
 *
 * Format:  {CATEGORY}-{WAREHOUSE}-{YYMM}{SEQ}{CHECK}
 * Example:  ELK-JKT01-260912345-7
 */
class SkuGenerator
{
    public static function generate(string $category, string $warehouseCode, int $sequence): string
    {
        $categoryCode = self::categoryCode($category);
        $warehouseCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $warehouseCode)) ?: 'WH';
        $yymm = date('ym');
        $seq = str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);

        $digits = $yymm . $seq;
        $check = self::checkDigit($digits);

        return "{$categoryCode}-{$warehouseCode}-{$yymm}{$seq}-{$check}";
    }

    private static function categoryCode(string $category): string
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $category);
        $letters = strtoupper($letters);

        if ($letters === '') {
            return 'GEN';
        }

        $consonants = preg_replace('/[AEIOU]/', '', $letters);
        $code = substr($consonants, 0, 3);

        if (strlen($code) < 3) {
            $code .= substr($letters, 0, 3 - strlen($code));
        }

        return str_pad($code, 3, 'X');
    }

    private static function checkDigit(string $digits): int
    {
        $sum = 0;
        $alt = false;

        foreach (array_reverse(str_split($digits)) as $d) {
            $d = (int) $d;
            if ($alt) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $alt = !$alt;
        }

        return (10 - ($sum % 10)) % 10;
    }
}