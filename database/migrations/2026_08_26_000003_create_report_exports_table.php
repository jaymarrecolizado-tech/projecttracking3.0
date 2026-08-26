<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // project | province
            $table->json('params');
            $table->string('status', 20)->default('PENDING')->index(); // PENDING|PROCESSING|DONE|FAILED
            $table->string('filename')->nullable();     // path on the local disk
            $table->string('download_name')->nullable(); // filename offered to the browser
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
