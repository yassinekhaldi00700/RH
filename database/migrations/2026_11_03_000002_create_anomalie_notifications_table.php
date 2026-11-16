<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('mysql')->create('anomalie_notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->string('employee_code', 50);
            $table->string('nom', 255);
            $table->string('email', 255);
            $table->date('work_date');
            $table->boolean('excluded')->default(false);
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_code', 'work_date']);
            $table->index('work_date');
        });
    }

    public function down()
    {
        Schema::connection('mysql')->dropIfExists('anomalie_notifications');
    }
};
