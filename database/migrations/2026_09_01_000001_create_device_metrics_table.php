<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Raw telemetry time-series (docs/FREEWIFI_MONITORING_PLAN.md §4.2).
        // One row per probe POST; pruned by metrics:prune after the retention window.
        Schema::create('device_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('ts')->useCurrent();
            $table->unsignedBigInteger('uptime_s')->nullable();
            $table->decimal('cpu_pct', 5, 2)->nullable();
            $table->decimal('mem_pct', 5, 2)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('clients')->nullable();
            $table->decimal('rx_mbps', 8, 2)->nullable();
            $table->decimal('tx_mbps', 8, 2)->nullable();
            $table->decimal('battery_v', 5, 2)->nullable();
            $table->decimal('solar_w', 8, 2)->nullable();
            $table->string('power_source', 30)->nullable();
            $table->string('firmware', 100)->nullable();
            $table->json('raw')->nullable();
            $table->index(['device_id', 'ts']);
            $table->index(['site_id', 'ts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_metrics');
    }
};
