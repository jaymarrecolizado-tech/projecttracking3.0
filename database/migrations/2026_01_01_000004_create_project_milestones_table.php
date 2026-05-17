<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('milestone_name', 200);
            $table->tinyInteger('milestone_order')->unsigned()->default(0);
            $table->decimal('weight_pct', 5, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index('project_id');
        });
    }
    public function down(): void { Schema::dropIfExists('project_milestones'); }
};
