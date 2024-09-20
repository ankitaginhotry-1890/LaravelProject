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
        // Schedule your commands here
        // $schedule->command('get:hello')->everyMinute();

        $schedule->command('get:hello')->everyThreeMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function scheduleTimezone()
    {
        return 'UTC'; // Adjust timezone as per your needs
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
