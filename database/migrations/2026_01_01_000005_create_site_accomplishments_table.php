<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('site_accomplishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->constrained('project_milestones')->cascadeOnDelete();
            $table->enum('status', ['NOT_STARTED','IN_PROGRESS','COMPLETED','ON_HOLD','CANCELLED'])->default('NOT_STARTED');
            $table->decimal('pct_complete', 5, 2)->default(0.00);
            $table->date('target_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['site_id', 'milestone_id']);
            $table->index('site_id');
            $table->index('milestone_id');
        });
    }
    public function down(): void { Schema::dropIfExists('site_accomplishments'); }
};
