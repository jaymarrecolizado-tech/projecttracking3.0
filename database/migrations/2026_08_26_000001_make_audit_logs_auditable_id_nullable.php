<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AuditLogMiddleware logs 'general' entries without a subject row;
        // the original NOT NULL only survived on non-strict MySQL.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('auditable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('auditable_id')->nullable(false)->change();
        });
    }
};
