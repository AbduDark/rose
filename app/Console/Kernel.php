<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // تعطيل الاشتراكات المنتهية الصلاحية كل ساعة
        $schedule->command('subscriptions:deactivate-expired')
                 ->hourly()
                 ->withoutOverlapping()
                 ->runInBackground();

        // إرسال تذكير للاشتراكات التي ستنتهي قريباً (كل يوم في الساعة 9 صباحاً)
        $schedule->command('subscriptions:send-expiry-reminders')
                 ->dailyAt('09:00')
                 ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}