<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Copy employee_code from users_schedules before dropping user_id
        DB::statement("
            ALTER TABLE user_day_schedules
            ADD COLUMN employee_code VARCHAR(50) NOT NULL DEFAULT '' AFTER id
        ");

        DB::statement("
            UPDATE user_day_schedules uds
            JOIN users_schedules us ON uds.user_id = us.id
            SET uds.employee_code = us.employee_code
        ");

        // Drop FK constraint then user_id column
        DB::statement("ALTER TABLE user_day_schedules DROP FOREIGN KEY user_day_schedules_user_id_foreign");
        DB::statement("ALTER TABLE user_day_schedules DROP COLUMN user_id");

        // Add index on employee_code
        DB::statement("ALTER TABLE user_day_schedules ADD INDEX idx_employee_code (employee_code)");

        // Fix unique constraint: was (user_id, weekday, ramadan) → now (employee_code, weekday, ramadan)
        DB::statement("ALTER TABLE user_day_schedules DROP INDEX user_day_schedules_user_id_weekday_ramadan_unique");
        DB::statement("ALTER TABLE user_day_schedules ADD UNIQUE KEY uq_emp_day_ramadan (employee_code, weekday, ramadan)");
    }

    public function down()
    {
        DB::statement("ALTER TABLE user_day_schedules DROP INDEX uq_emp_day_ramadan");
        DB::statement("ALTER TABLE user_day_schedules DROP INDEX idx_employee_code");
        DB::statement("ALTER TABLE user_day_schedules ADD COLUMN user_id INT NOT NULL DEFAULT 0 AFTER id");
        DB::statement("ALTER TABLE user_day_schedules DROP COLUMN employee_code");
    }
};
