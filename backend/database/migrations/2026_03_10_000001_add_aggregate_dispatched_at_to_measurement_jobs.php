<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measurement_jobs', function (Blueprint $table) {
            $table->timestamp('aggregate_dispatched_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('measurement_jobs', function (Blueprint $table) {
            $table->dropColumn('aggregate_dispatched_at');
        });
    }
};
