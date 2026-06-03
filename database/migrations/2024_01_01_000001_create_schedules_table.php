<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('mysql')->create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->time('morning_start');
            $table->time('lunch_start');
            $table->time('lunch_end');
            $table->time('workday_end');
            $table->time('morning_threshold');
            $table->time('lunch_threshold');
            $table->time('afternoon_threshold');
            $table->time('evening_threshold');
            $table->integer('morning_penalty')->default(0);
            $table->integer('lunch_penalty')->default(0);
            $table->integer('afternoon_penalty')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('mysql')->dropIfExists('schedules');
    }
};
