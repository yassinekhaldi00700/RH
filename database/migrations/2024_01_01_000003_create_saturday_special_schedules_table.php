<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('mysql')->create('saturday_special_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users_schedules')->onDelete('cascade');
            $table->foreignId('schedule_id')->constrained('schedules')->onDelete('cascade');
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down()
    {
        Schema::connection('mysql')->dropIfExists('saturday_special_schedules');
    }
};
