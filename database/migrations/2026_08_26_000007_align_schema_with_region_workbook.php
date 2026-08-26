<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns the schema with the real REGION II SITE STATUS workbook:
 * roster sheets carry per-AP MACs/brands and provider/classification metadata,
 * telemetry sheets use NO NMS / DOWN SERVER day statuses, and site lifecycle
 * values (ONGOING, EXPIRED, REBATES, SUPPORT, REMOVED) need a raw home.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('site_classification', 80)->nullable();
            $table->string('cms_provider', 100)->nullable();
            $table->string('link_provider', 100)->nullable();
            $table->string('source_of_bw', 150)->nullable();
            $table->string('loc_id', 50)->nullable();
            $table->string('prov_id', 50)->nullable();
            $table->string('lifecycle_status', 40)->nullable()->index();
            $table->boolean('accepted')->nullable();
            $table->string('ap_brand', 80)->nullable();
            $table->date('declaration_date')->nullable();
            $table->date('integrated_date')->nullable();
            $table->string('school_id', 30)->nullable()->index();
        });

        // NO NMS = AP reachable but management plane silent; DOWN SERVER = backhaul/server outage.
        Schema::table('site_daily_statuses', function (Blueprint $table) {
            $table->enum('status', ['UP', 'DOWN', 'NO_DATA', 'NO_NMS', 'DOWN_SERVER'])->change();
        });

        Schema::table('freewifi_import_batches', function (Blueprint $table) {
            $table->enum('type', ['sites', 'devices', 'region_workbook'])->default('sites')->change();
        });
    }

    public function down(): void
    {
        Schema::table('freewifi_import_batches', function (Blueprint $table) {
            $table->enum('type', ['sites', 'devices'])->default('sites')->change();
        });
        Schema::table('site_daily_statuses', function (Blueprint $table) {
            $table->enum('status', ['UP', 'DOWN', 'NO_DATA'])->change();
        });
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'site_classification', 'cms_provider', 'link_provider', 'source_of_bw',
                'loc_id', 'prov_id', 'lifecycle_status', 'accepted', 'ap_brand',
                'declaration_date', 'integrated_date', 'school_id',
            ]);
        });
    }
};
