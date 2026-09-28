<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Resolves a raw provider string from a site column to its registry code
 * (Plan.md #4a), so "which company is this" has one answer instead of a
 * free-text string that varies in case and punctuation.
 *
 * Fail-closed by design: an unrecognised value returns null and is *reported*,
 * never guessed into the nearest match. A registry that silently maps
 * "Philcomsat Satellite" onto PHILCOMSAT would be indistinguishable, from the
 * data, from one that invents a provider that does not exist.
 *
 * Only three columns are provider names: cms_provider, link_provider and
 * isp_provider. last_mile_tech and source_of_bw are vocabularies, not
 * companies, and are deliberately not routed through here.
 */
class ProviderRegistry
{
    /**
     * Canonical codes keyed by code, from config/providers.php.
     *
     * @return array<string, array{label: string, aliases: list<string>, default_transport: string|null}>
     */
    private function providers(): array
    {
        return config('providers') ?? [];
    }

    /**
     * Registry code for a raw column value, or null when it is blank or unknown.
     *
     * Matching is case- and whitespace-insensitive, and also ignores a
     * trailing "Inc."/"Ltd." so "Data Lake Inc" and "Data Lake Inc." land on the
     * same provider — that class of variation is what made the raw columns
     * unusable for a per-provider rollup in the first place.
     */
    public function code(?string $raw): ?string
    {
        $needle = $this->fold($raw);

        if ($needle === '') {
            return null;
        }

        foreach ($this->providers() as $code => $provider) {
            if ($this->fold($provider['label']) === $needle || $this->fold($code) === $needle) {
                return $code;
            }
            foreach ($provider['aliases'] as $alias) {
                if ($this->fold($alias) === $needle) {
                    return $code;
                }
            }
        }

        return null;
    }

    /** Canonical spelling for a code (or for a raw value, resolved first). */
    public function label(?string $codeOrRaw): ?string
    {
        $code = $this->code($codeOrRaw);

        return $code === null ? null : ($this->providers()[$code]['label'] ?? null);
    }

    /** @return list<string> every registered code, in config order. */
    public function codes(): array
    {
        return array_keys($this->providers());
    }

    /** Modal transport for a provider, or null when it has no sites. */
    public function defaultTransport(?string $codeOrRaw): ?string
    {
        $resolved = $this->code($codeOrRaw);
        if ($resolved === null) {
            return null;
        }

        return $this->providers()[$resolved]['default_transport'] ?? null;
    }

    /**
     * Raw values present in a column that the registry does not recognise.
     *
     * @param  iterable<string|null>  $values
     * @return array<string, int> raw value => count, most frequent first
     */
    public function unmapped(iterable $values): array
    {
        $unknown = [];
        foreach ($values as $value) {
            if ($value === null || trim($value) === '') {
                continue;
            }
            if ($this->code($value) === null) {
                $unknown[$value] = ($unknown[$value] ?? 0) + 1;
            }
        }
        arsort($unknown);

        return $unknown;
    }

    /** Case-folded, trimmed, and stripped of a trailing corporate suffix. */
    private function fold(?string $value): string
    {
        $folded = Str::of((string) $value)->trim()->lower()->toString();
        $folded = preg_replace('/[,\s]+(inc|llc|ltd|corporation|corp)\.?$/', '', $folded) ?? $folded;

        return trim($folded, ' ,.');
    }
}
