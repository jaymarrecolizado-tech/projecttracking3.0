<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // UP/DOWN episode tracking (docs §4.2). One open row per site; closed
        // when the site reports healthy again.
        Schema::create('site_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->enum('cause', ['heartbeat_lost', 'poll_failed', 'manual', 'planned_maintenance'])->nullable();
            $table->timestamps();
            $table->index(['site_id', 'resolved_at']);
            $table->index('started_at');
        });

        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('metric', 40);
            $table->enum('operator', ['<', '<=', '>', '>=', '==']);
            $table->decimal('threshold', 12, 2);
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning');
            $table->json('notify_roles')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('name');
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('alert_rules')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('triggered_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('escalation_level')->default(0);
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['rule_id', 'resolved_at']);
            $table->index('site_id');
        });

        Schema::create('device_metric_hourlies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('hour');
            $table->decimal('latency_avg', 10, 2)->nullable();
            $table->decimal('latency_max', 10, 2)->nullable();
            $table->unsignedInteger('clients_max')->nullable();
            $table->decimal('rx_avg', 10, 2)->nullable();
            $table->decimal('tx_avg', 10, 2)->nullable();
            $table->decimal('battery_min', 6, 2)->nullable();
            $table->unsignedInteger('samples')->default(0);
            $table->timestamps();
            $table->unique(['device_id', 'hour']);
            $table->index('hour');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_metric_hourlies');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('site_status_events');
    }
};
