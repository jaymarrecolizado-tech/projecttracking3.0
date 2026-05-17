<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('freewifi_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 255);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_success')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->json('error_log')->nullable();
            $table->enum('job_status', ['PENDING','PROCESSING','DONE','FAILED'])->default('PENDING');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('freewifi_import_batches'); }
};
