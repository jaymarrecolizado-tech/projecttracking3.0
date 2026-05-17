<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('accomplishment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accomplishment_id')->constrained('site_accomplishments')->cascadeOnDelete();
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->decimal('old_pct', 5, 2)->nullable();
            $table->decimal('new_pct', 5, 2)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->index('accomplishment_id');
        });
    }
    public function down(): void { Schema::dropIfExists('accomplishment_history'); }
};
