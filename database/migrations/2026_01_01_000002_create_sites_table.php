<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('nationwide_id', 50)->nullable();
            $table->string('ap_site_code', 50)->nullable();
            $table->string('location_name');
            $table->string('ap_site_name')->nullable();
            $table->string('site_type', 80)->nullable();
            $table->string('barangay')->nullable();
            $table->string('municipality')->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('region')->nullable();
            $table->enum('island_group', ['Luzon','Visayas','Mindanao'])->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->date('date_of_activation')->nullable();
            $table->enum('status', ['planned','active','inactive','decommissioned','maintenance'])->default('planned');
            $table->string('isp_provider', 100)->nullable();
            $table->string('last_mile_tech', 80)->nullable();
            $table->decimal('bw_download_cir', 10, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['latitude', 'longitude']);
            $table->index(['province', 'municipality']);
            $table->index(['region', 'island_group']);
            $table->index('status');
            $table->unique(['project_id', 'ap_site_code']);
        });
    }
    public function down(): void { Schema::dropIfExists('sites'); }
};
