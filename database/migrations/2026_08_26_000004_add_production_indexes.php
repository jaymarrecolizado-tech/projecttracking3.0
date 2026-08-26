<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Warranty filter chips on Devices/Index scan these constantly.
            $table->index('warranty_until');
        });

        Schema::table('freewifi_import_batches', function (Blueprint $table) {
            $table->index(['job_status', 'created_at']);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $t) => $t->dropIndex(['warranty_until']));
        Schema::table('freewifi_import_batches', fn (Blueprint $t) => $t->dropIndex(['job_status', 'created_at']));
        Schema::table('sites', fn (Blueprint $t) => $t->dropIndex(['project_id', 'status']));
    }
};
