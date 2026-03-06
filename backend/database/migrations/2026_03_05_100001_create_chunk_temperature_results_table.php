<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chunk_temperature_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_job_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->string('city', 255);
            $table->decimal('min_temp', 8, 1);
            $table->decimal('max_temp', 8, 1);
            $table->decimal('sum_temp', 16, 2);
            $table->unsignedBigInteger('count');
            $table->timestamps();

            $table->index(['measurement_job_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chunk_temperature_results');
    }
};
