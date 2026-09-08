<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan_revision §Phase 3.4 — identifier search moves to anchored-prefix LIKE,
 * which only pays off when these columns are actually indexed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->index('asset_tag', 'devices_asset_tag_index');
            $table->index('serial_number', 'devices_serial_number_index');
            $table->index('mac_address', 'devices_mac_address_index');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('devices_asset_tag_index');
            $table->dropIndex('devices_serial_number_index');
            $table->dropIndex('devices_mac_address_index');
        });
    }
};
