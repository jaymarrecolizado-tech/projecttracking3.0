<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('site_daily_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['UP','DOWN','NO_DATA'])->default('NO_DATA');
            $table->unsignedInteger('total_unique_users')->nullable();
            $table->decimal('bandwidth_utilization_mbps', 10, 4)->nullable();
            $table->decimal('uptime_percent', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->enum('entry_status', ['DRAFT','SUBMITTED','APPROVED','LOCKED'])->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['site_id', 'date']);
            $table->index('date');
            $table->index('status');
            $table->index('entry_status');
        });
    }
    public function down(): void { Schema::dropIfExists('site_daily_statuses'); }
};
