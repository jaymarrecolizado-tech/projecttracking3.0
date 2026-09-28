<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\ProviderRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Collapse the free-text provider columns onto the registry (Plan.md #4b).
 *
 * An artisan command and not a migration, for the same reason
 * `sites:backfill-regions` is one: migrations run before the workbook import,
 * so a migration would normalize zero rows and then the import would write the
 * dirty values straight back.
 *
 * Dry run by default. `--apply` writes, and is idempotent: a second run finds
 * nothing to change. Raw spellings that the registry does not recognise are
 * *reported and left alone* — a value nobody recognises is a question for the
 * owner, not something to fold into the nearest match.
 */
class NormalizeSiteProviders extends Command
{
    protected $signature = 'providers:normalize
        {--apply : Write the canonical spellings (default is a dry run)}
        {--chunk=500 : Rows per query}';

    protected $description = 'Normalize sites.cms_provider / link_provider / isp_provider to the provider registry';

    /** The only three columns that hold a company name. */
    private const COLUMNS = ['cms_provider', 'link_provider', 'isp_provider'];

    public function handle(ProviderRegistry $registry): int
    {
        $apply = (bool) $this->option('apply');
        $chunk = max(1, (int) $this->option('chunk'));

        $unknown = $this->unknownValues($registry);
        $changed = 0;
        $scanned = 0;

        Site::query()
            ->where(function ($query) {
                foreach (self::COLUMNS as $column) {
                    $query->orWhereNotNull($column);
                }
            })
            ->orderBy('id')
            ->chunk($chunk, function ($sites) use ($registry, $apply, &$changed, &$scanned) {
                foreach ($sites as $site) {
                    $updates = [];
                    $raw = [];

                    foreach (self::COLUMNS as $column) {
                        $value = $site->{$column};
                        if ($value === null || trim($value) === '') {
                            continue;
                        }
                        $canonical = $registry->label($value);
                        if ($canonical !== null && $canonical !== $value) {
                            $updates[$column] = $canonical;
                            // Keep the operator's original spelling for audit —
                            // the workbook's "IT Business Solutions" and
                            // "IT BUSINESS SOLUTIONS" are the same company, but
                            // neither is the one string we chose.
                            $raw[$column] = $value;
                        }
                    }

                    $scanned++;
                    if ($updates === []) {
                        continue;
                    }

                    $changed++;
                    if (! $apply) {
                        continue;
                    }

                    if ($raw !== []) {
                        $metadata = array_merge($site->metadata ?? [], ['provider_raw' => $raw]);
                        $updates['metadata'] = json_encode($metadata);
                    }

                    DB::transaction(fn () => Site::whereKey($site->id)->update($updates));
                }
            });

        $this->reportUnmapped($unknown);
        $this->info(sprintf(
            '%s %d of %d site(s) with a provider value.',
            $apply ? 'Normalized' : 'Would normalize',
            $changed,
            $scanned
        ));

        if (! $apply && $changed > 0) {
            $this->line('Dry run — nothing written. Re-run with --apply to execute.');
        }

        return self::SUCCESS;
    }

    /**
     * Every raw spelling the registry does not know, with its row count.
     *
     * @return array<string, int>
     */
    private function unknownValues(ProviderRegistry $registry): array
    {
        $values = [];
        foreach (self::COLUMNS as $column) {
            foreach (Site::query()->whereNotNull($column)->distinct()->pluck($column) as $value) {
                $values[] = $value;
            }
        }

        return $registry->unmapped($values);
    }

    /** @param  array<string, int>  $unknown */
    private function reportUnmapped(array $unknown): void
    {
        if ($unknown === []) {
            $this->info('Every provider value in the dataset maps to the registry.');

            return;
        }

        $this->warn('Unrecognised provider values (left untouched — add them to config/providers.php):');
        foreach ($unknown as $value => $count) {
            $this->line(sprintf('  %-40s %d site(s)', $value, $count));
        }
    }
}
