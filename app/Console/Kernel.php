<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('xero:sync-contact-number')
            ->everyThirtyMinutes()
            ->withoutOverlapping(25)   // lock 25 menit
            ->onOneServer()
            ->runInBackground();

        $schedule->call(function () {
            \App\Models\SyncJobStatus::where('job_type', 'sync_xero_contact_number')
                ->whereIn('status', ['queued', 'running'])
                ->where('updated_at', '<', now()->subHour())
                ->update([
                    'status' => 'failed',
                    'error_message' => 'Timeout/stuck > 1 jam',
                    'finished_at' => now(),
                ]);
        })->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
