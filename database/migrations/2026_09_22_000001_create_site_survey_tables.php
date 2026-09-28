<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Questionnaire definition kept as data, not code, so a v2 question set
        // is a new row rather than a migration (Plan.md S3).
        Schema::create('site_surveys', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('title', 160);
            $table->json('questions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('is_active');
        });

        // One row per submission. Deliberately holds no PII (Plan.md S3):
        // the respondent is never identified, only the site they were on.
        Schema::create('site_survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_id')->constrained('site_surveys')->cascadeOnDelete();

            // The code as submitted, kept even after resolution to the canonical
            // site — it is how a bad merge is debugged after the fact.
            $table->string('ap_site_code', 100)->nullable();

            // Provider/transport denormalized at submit time, so a later site
            // re-assignment does not rewrite the history a report was built on.
            $table->string('cms_provider', 100)->nullable();
            $table->string('last_mile_tech', 40)->nullable();

            $table->json('ratings');
            $table->text('comments')->nullable();

            // sha256(ip + APP_KEY) — lets duplicate suppression work without
            // ever storing an address.
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['site_id', 'submitted_at']);
            $table->index(['survey_id', 'submitted_at']);
            $table->index('ip_hash');
            $table->index(['cms_provider', 'last_mile_tech']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_survey_responses');
        Schema::dropIfExists('site_surveys');
    }
};
