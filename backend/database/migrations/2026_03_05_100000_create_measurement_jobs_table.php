<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('requested_rows');
            $table->string('status', 32)->default('pending'); // pending, generating, processing, aggregating, completed, failed
            $table->string('file_path')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedInteger('rows_processed')->default(0);
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->unsignedBigInteger('memory_used_bytes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_jobs');
    }
};
