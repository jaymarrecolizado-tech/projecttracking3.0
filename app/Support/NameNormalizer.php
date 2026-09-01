<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Shared normalizer for barangay/LGU name matching: strips accents,
 * parenthesized disambiguators ("(Capital)"), "City of " prefixes and
 * " City" suffixes so site spellings, PSA names and OCHA names collide
 * correctly.
 */
class NameNormalizer
{
    public static function normalize(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $name = Str::ascii(trim($name));
        $name = preg_replace('/\([^)]*\)/', '', $name);
        $name = preg_replace('/^city of /i', '', trim((string) $name));
        $name = trim((string) preg_replace('/\bcity$/i', '', trim((string) $name)));

        return trim((string) preg_replace('/[^a-z0-9 ]+/', '', strtolower($name)));
    }
}
