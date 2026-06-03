<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('mysql')->create('user_day_schedules', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->foreign('user_id')->references('id')->on('users_schedules')->onDelete('cascade');
            $table->tinyInteger('weekday')->unsigned(); // 0=Monday, 1=Tuesday, ..., 6=Sunday
            $table->integer('schedule_id');
            $table->foreign('schedule_id')->references('id')->on('schedules')->onDelete('cascade');
            $table->tinyInteger('ramadan')->default(0); // 0=normal, 1=ramadan
            $table->timestamps();
            $table->unique(['user_id', 'weekday', 'ramadan']);
        });
    }

    public function down()
    {
        Schema::connection('mysql')->dropIfExists('user_day_schedules');
    }
};
