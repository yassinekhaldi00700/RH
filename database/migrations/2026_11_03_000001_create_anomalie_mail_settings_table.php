<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('mysql')->create('anomalie_mail_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        // Single settings row, always id=1.
        DB::table('anomalie_mail_settings')->insert([
            'enabled'    => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        Schema::connection('mysql')->dropIfExists('anomalie_mail_settings');
    }
};
