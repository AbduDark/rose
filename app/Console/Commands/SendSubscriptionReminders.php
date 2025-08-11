<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendSubscriptionReminders extends Command
{
    protected $signature = 'subscriptions:send-reminders';
    protected $description = 'إرسال تذكيرات للطلبة عند اقتراب انتهاء اشتراكاتهم';

    public function handle()
    {
        // تذكير 3 أيام قبل الانتهاء
        $this->sendReminders(3);

        // تذكير يوم واحد قبل الانتهاء
        $this->sendReminders(1);

        return 0;
    }

    private function sendReminders($days)
    {
        $targetDate = now()->addDays($days)->format('Y-m-d');

        $expiringSubscriptions = Subscription::where('is_active', true)
            ->where('status', 'approved')
            ->whereDate('expires_at', $targetDate)
            ->get();

        foreach ($expiringSubscriptions as $subscription) {
            NotificationService::subscriptionExpiringReminder(
                $subscription->user_id,
                $subscription->course_id,
                $days
            );
        }

        $count = $expiringSubscriptions->count();
        $this->info("تم إرسال {$count} تذكير للاشتراكات التي ستنتهي خلال {$days} أيام.");
    }
}
