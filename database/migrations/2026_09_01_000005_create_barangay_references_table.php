<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Authoritative barangay list per LGU (Plan: barangay coverage report).
        // Seeded from the boundary layer via barangays:sync-reference; rows can
        // be corrected/added to match PSA counts without touching the polygons.
        Schema::create('barangay_references', function (Blueprint $table) {
            $table->id();
            $table->string('province', 100);
            $table->string('municipality', 100);
            $table->string('name', 150);
            $table->string('name_normalized', 150);
            $table->string('psgc', 20)->nullable();
            $table->timestamps();
            $table->unique(['province', 'municipality', 'name_normalized']);
            $table->index(['province', 'municipality']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangay_references');
    }
};
