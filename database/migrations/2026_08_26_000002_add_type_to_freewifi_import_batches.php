<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freewifi_import_batches', function (Blueprint $table) {
            $table->enum('type', ['sites', 'devices'])->default('sites')->after('filename');
        });
        DB::table('freewifi_import_batches')->whereNull('type')->update(['type' => 'sites']);
    }

    public function down(): void
    {
        Schema::table('freewifi_import_batches', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
