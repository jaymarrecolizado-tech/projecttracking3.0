<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Municipality → legislative district lookup (Plan §Map 4.1). Kept as
        // data, not code, so Sites, Map and Reports all resolve consistently.
        Schema::create('legislative_districts', function (Blueprint $table) {
            $table->id();
            $table->string('province', 100);
            $table->string('municipality', 100);
            $table->string('district', 100);
            $table->timestamps();
            $table->unique(['province', 'municipality']);
            $table->index('district');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->index('district');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropIndex(['district']);
        });
        Schema::dropIfExists('legislative_districts');
    }
};
