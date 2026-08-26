<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalog of spec sheets (see docs/FREEWIFI_MONITORING_PLAN.md §3.1)
        Schema::create('device_models', function (Blueprint $table) {
            $table->id();
            $table->string('manufacturer', 100);
            $table->string('model_name', 120);
            $table->string('model_number', 120);
            $table->enum('type', ['outdoor_ap', 'router', 'switch', 'cpe', 'solar_panel', 'charge_controller', 'battery', 'ups', 'poe_injector', 'antenna', 'camera', 'other']);
            $table->enum('wifi_standard', ['wifi4', 'wifi5', 'wifi6', 'wifi6e', 'wifi7', 'none'])->default('none');
            $table->json('specs')->nullable();
            $table->string('datasheet_url')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['manufacturer', 'model_number']);
            $table->index('type');
        });

        // Each physical unit
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_model_id')->constrained()->restrictOnDelete();
            $table->string('asset_tag')->unique();
            $table->string('serial_number')->unique();
            $table->string('mac_address')->nullable()->unique();
            $table->string('firmware_version')->nullable();
            $table->enum('status', ['in_stock', 'deployed', 'under_repair', 'retired', 'lost'])->default('in_stock');
            $table->enum('condition', ['new', 'good', 'degraded', 'faulty'])->default('new');
            $table->string('purchase_order_no')->nullable();
            $table->string('supplier', 150)->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->date('purchased_at')->nullable();
            $table->date('warranty_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
            $table->index(['status', 'condition']);
        });

        // Assignment history — many-to-many over time
        Schema::create('device_deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->enum('role_at_site', ['primary_ap', 'backup_ap', 'backhaul', 'power', 'surveillance', 'other'])->default('primary_ap');
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('install_notes')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'removed_at']);
            $table->index(['site_id', 'removed_at']);
        });

        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['inspection', 'repair', 'firmware_upgrade', 'replacement', 'cleaning', 'reboot']);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->unsignedInteger('downtime_minutes')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->text('findings')->nullable();
            $table->text('actions_taken')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'performed_at']);
            $table->index(['site_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('device_deployments');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('device_models');
    }
};
