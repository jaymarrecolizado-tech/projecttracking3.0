<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'])->default('OPEN')->index();
            $table->enum('category', ['connectivity', 'hardware', 'power', 'firmware', 'other'])->default('other');
            $table->foreignId('reported_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index(['site_id', 'created_at']);
        });

        // New permission ships with the module; seeder matrix also updated.
        DB::table('permissions')->updateOrInsert(['name' => 'tickets.manage']);
        $permId = DB::table('permissions')->where('name', 'tickets.manage')->value('id');
        foreach (['admin', 'project_manager'] as $role) {
            $roleId = DB::table('roles')->where('name', $role)->value('id');
            if ($roleId) {
                DB::table('role_permission')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tickets');
        if (($permId = DB::table('permissions')->where('name', 'tickets.manage')->value('id'))) {
            DB::table('role_permission')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
};
