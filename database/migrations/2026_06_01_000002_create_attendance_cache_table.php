<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_cache', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50);
            $table->date('work_date');
            $table->string('employee_id', 50);
            $table->text('row_json');
            $table->timestamp('computed_at')->useCurrent();

            // Fast lookup by category + date range
            $table->index(['category', 'work_date'], 'idx_cache_category_date');

            // Prevent duplicate rows; also used by upsert
            $table->unique(['category', 'work_date', 'employee_id'], 'uq_cache_row');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_cache');
    }
};
