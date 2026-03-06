<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_job_id')->constrained()->cascadeOnDelete();
            $table->string('phase', 32)->nullable(); // generating, chunk_1, chunk_2, aggregating
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->unsignedBigInteger('memory_used_bytes')->nullable();
            $table->unsignedBigInteger('rows_processed')->default(0);
            $table->timestamps();

            $table->index('measurement_job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_metrics');
    }
};
