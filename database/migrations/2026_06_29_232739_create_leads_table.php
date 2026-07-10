<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            // Identity (kept clean, never overloaded with opportunity data).
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Pipeline.
            $table->string('source')->default('manual');
            $table->string('form_type')->nullable();
            $table->string('stage')->default('new');
            $table->decimal('value', 12, 2)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lost_reason')->nullable();
            $table->string('win_reason')->nullable();

            // Attribution.
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('referrer')->nullable();
            $table->string('landing_page')->nullable();

            // Lifecycle timestamps.
            $table->timestamp('first_contacted_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->foreignId('merged_into_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->timestamps();

            $table->index('email');
            $table->index('phone');
            $table->index('stage');
            $table->index('owner_id');
            $table->index('archived_at');
            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
