<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('mysql')->create('user_workdays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users_schedules')->onDelete('cascade');
            $table->integer('weekday');
            $table->timestamps();
            $table->unique(['user_id', 'weekday']);
        });
    }

    public function down()
    {
        Schema::connection('mysql')->dropIfExists('user_workdays');
    }
};
