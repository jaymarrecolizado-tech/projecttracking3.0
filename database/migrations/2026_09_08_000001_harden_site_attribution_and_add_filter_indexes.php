<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan_revision §Phase 1.3 + §Phase 1.5 + §Phase 3.1.
 *
 * - sites.region backfilled from province (the region filter silently drops
 *   unattributed sites, and 86% of live rows were blank at audit time).
 * - sites.ap_site_code made NOT NULL: the (project_id, ap_site_code) unique
 *   index cannot dedupe NULL pairs on MySQL, which voided the guarantee.
 * - Composite filter indexes the map and coverage queries actually use.
 */
return new class extends Migration
{
    private const REGIONS_BY_PROVINCE = [
        'Batanes' => 'II',
        'Cagayan' => 'II',
        'Isabela' => 'II',
        'Nueva Vizcaya' => 'II',
        'Quirino' => 'II',
    ];

    public function up(): void
    {
        foreach (self::REGIONS_BY_PROVINCE as $province => $region) {
            DB::table('sites')
                ->where('province', $province)
                ->where(fn ($q) => $q->whereNull('region')->orWhere('region', ''))
                ->update(['region' => $region]);
        }

        // `||` is SQLite concat; MySQL needs CONCAT().
        $syntheticCode = DB::getDriverName() === 'sqlite'
            ? DB::raw("'NS-MIG-' || id")
            : DB::raw("CONCAT('NS-MIG-', id)");

        DB::table('sites')
            ->where(fn ($q) => $q->whereNull('ap_site_code')->orWhere('ap_site_code', ''))
            ->update(['ap_site_code' => $syntheticCode]);

        Schema::table('sites', function (Blueprint $table) {
            $table->string('ap_site_code', 50)->nullable(false)->change();

            $table->index(['province', 'district'], 'sites_province_district_index');
            $table->index(['municipality', 'barangay'], 'sites_municipality_barangay_index');
            $table->index('site_type', 'sites_site_type_index');
        });

        Schema::table('site_daily_statuses', function (Blueprint $table) {
            $table->index(['date', 'status'], 'sds_date_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('site_daily_statuses', function (Blueprint $table) {
            $table->dropIndex('sds_date_status_index');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropIndex('sites_province_district_index');
            $table->dropIndex('sites_municipality_barangay_index');
            $table->dropIndex('sites_site_type_index');
            $table->string('ap_site_code', 50)->nullable()->change();
        });
    }
};
