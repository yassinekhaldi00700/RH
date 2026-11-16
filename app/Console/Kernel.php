<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Pre-compute attendance data for the last 2 months every night at 20:00.
        // After this runs, users requesting any range within the last 2 months
        // will be served instantly from the attendance_cache table.
        $schedule->command('attendance:precompute')->dailyAt('17:11');

        // Anomalie notification emails: build the list in the morning, then send
        // two hours later — the gap gives an admin a window to review/exclude
        // people or pause sending entirely from the "Notifications Anomalies" page.
        $schedule->command('anomalie:build')->weekdays()->at('07:00');
        $schedule->command('anomalie:send')->weekdays()->at('09:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
