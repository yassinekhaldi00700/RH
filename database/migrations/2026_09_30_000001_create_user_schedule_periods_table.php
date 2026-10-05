<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('mysql')->create('user_schedule_periods', function (Blueprint $table) {
            $table->increments('id');
            $table->string('employee_code', 50);
            $table->string('full_name', 255);
            $table->unsignedInteger('schedule_id');
            $table->foreign('schedule_id')->references('id')->on('schedules')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
            $table->index('employee_code');
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down()
    {
        Schema::connection('mysql')->dropIfExists('user_schedule_periods');
    }
};
