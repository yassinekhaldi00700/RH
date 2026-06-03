<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('mysql')->create('users_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->string('employee_name');
            $table->foreignId('schedule_id')->nullable()->constrained('schedules')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('mysql')->dropIfExists('users_schedules');
    }
};
