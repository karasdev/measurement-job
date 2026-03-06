<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temperature_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_job_id')->constrained()->cascadeOnDelete();
            $table->string('city', 255);
            $table->decimal('min_temp', 8, 1);
            $table->decimal('max_temp', 8, 1);
            $table->decimal('avg_temp', 8, 1);
            $table->unsignedBigInteger('count');
            $table->timestamps();

            $table->unique(['measurement_job_id', 'city']);
            $table->index('measurement_job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temperature_results');
    }
};
