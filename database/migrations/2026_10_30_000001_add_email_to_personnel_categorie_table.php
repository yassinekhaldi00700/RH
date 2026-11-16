<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('mysql')->table('personnel_categorie', function (Blueprint $table) {
            $table->string('email', 255)->nullable()->after('nom');
        });
    }

    public function down()
    {
        Schema::connection('mysql')->table('personnel_categorie', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
