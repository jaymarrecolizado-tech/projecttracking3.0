<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Services\ProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Provider registry (Plan.md #4a/#4b).
 *
 * The reason this exists is one number: the workbook carries twelve distinct
 * `cms_provider` strings but only eleven companies, because "IT BUSINESS
 * SOLUTIONS" (139 sites) and "IT Business Solutions" (1) are the same provider
 * and a per-provider rollup reported them separately. The 1-site row was
 * invisible next to its own siblings.
 */
class ProviderRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function registry(): ProviderRegistry
    {
        return app(ProviderRegistry::class);
    }

    public function test_case_and_spacing_variants_resolve_to_one_provider(): void
    {
        $registry = $this->registry();

        $this->assertSame('it-business-solutions', $registry->code('IT BUSINESS SOLUTIONS'));
        $this->assertSame('it-business-solutions', $registry->code('IT Business Solutions'));
        $this->assertSame('it-business-solutions', $registry->code('  it business solutions, inc.  '));
        $this->assertSame('dict', $registry->code('dict'));
        $this->assertSame('philcomsat', $registry->code('  PHILCOMSAT  '));
    }

    public function test_a_code_resolves_to_its_canonical_label(): void
    {
        $registry = $this->registry();

        $this->assertSame('IT Business Solutions', $registry->label('IT BUSINESS SOLUTIONS'));
        $this->assertSame('Data Lake Inc.', $registry->label('Data Lake Inc'));
        $this->assertSame('PHILCOMSAT', $registry->label('philcomsat'));
    }

    /** Fail-closed: an unknown value is a question for the owner, not a guess. */
    public function test_an_unrecognised_value_is_not_guessed_into_a_neighbour(): void
    {
        $registry = $this->registry();

        $this->assertNull($registry->code('Totally Unknown Telecom'));
        $this->assertNull($registry->label('Totally Unknown Telecom'));
        $this->assertNull($registry->code(''));
        $this->assertNull($registry->code(null));
    }

    public function test_unmapped_counts_only_the_unknown_values(): void
    {
        $unmapped = $this->registry()->unmapped([
            'DICT', 'DICT', 'DICT',
            'Nowhere Telecom', 'Nowhere Telecom', 'Nowhere Telecom', 'Nowhere Telecom',
            'Other Co',
            null, '', '   ',
        ]);

        $this->assertSame(['Nowhere Telecom' => 4, 'Other Co' => 1], $unmapped);
    }

    public function test_default_transport_is_descriptive_and_may_be_unknown(): void
    {
        $registry = $this->registry();

        $this->assertSame('RADIO', $registry->defaultTransport('DICT'));
        $this->assertSame('FIBER', $registry->defaultTransport('Converge ICT Solutions, Inc.'));
        $this->assertNull($registry->defaultTransport('Innove'), 'Innove only ever appears as a link provider.');
        $this->assertNull($registry->defaultTransport('Nowhere Telecom'));
    }

    public function test_normalize_is_a_dry_run_by_default(): void
    {
        $site = Site::factory()->create([
            'cms_provider' => 'IT BUSINESS SOLUTIONS',
            'link_provider' => 'IT BUSINESS SOLUTIONS',
            'isp_provider' => null,
        ]);

        $this->artisan('providers:normalize')
            ->expectsOutputToContain('Would normalize 1 of 1 site(s)')
            ->assertSuccessful();

        $this->assertSame('IT BUSINESS SOLUTIONS', $site->fresh()->cms_provider, 'A dry run must not write.');
    }

    public function test_normalize_folds_the_split_provider_and_keeps_the_raw_spelling(): void
    {
        $site = Site::factory()->create([
            'cms_provider' => 'IT BUSINESS SOLUTIONS',
            'link_provider' => '  philcomsat ',
            'metadata' => ['merged_into' => null],
        ]);

        $this->artisan('providers:normalize', ['--apply' => true])->assertSuccessful();
        $site->refresh();

        $this->assertSame('IT Business Solutions', $site->cms_provider);
        $this->assertSame('PHILCOMSAT', $site->link_provider);
        // The operator's own spelling is kept, so an audit can still see what
        // the workbook actually said.
        $this->assertSame('IT BUSINESS SOLUTIONS', $site->metadata['provider_raw']['cms_provider']);
        $this->assertSame('  philcomsat ', $site->metadata['provider_raw']['link_provider']);
    }

    public function test_normalize_is_idempotent(): void
    {
        Site::factory()->create(['cms_provider' => 'IT BUSINESS SOLUTIONS']);

        $this->artisan('providers:normalize', ['--apply' => true])->assertSuccessful();
        // Second run has nothing left to do, and must not stash a raw spelling
        // for a value it already normalised.
        $this->artisan('providers:normalize', ['--apply' => true])
            ->expectsOutputToContain('Normalized 0 of 1 site(s)')
            ->assertSuccessful();

        $site = Site::first();
        $this->assertSame(['provider_raw' => ['cms_provider' => 'IT BUSINESS SOLUTIONS']], $site->metadata);
    }

    public function test_normalize_reports_unmapped_values_instead_of_dropping_them(): void
    {
        $site = Site::factory()->create(['cms_provider' => 'Nowhere Telecom']);

        $this->artisan('providers:normalize', ['--apply' => true])
            ->expectsOutputToContain('Unrecognised provider values')
            ->expectsOutputToContain('Nowhere Telecom')
            ->assertSuccessful();

        $this->assertSame('Nowhere Telecom', $site->fresh()->cms_provider);
    }
}
