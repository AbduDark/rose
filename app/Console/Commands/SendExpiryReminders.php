<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class SendExpiryReminders extends Command
{
    protected $signature = 'subscriptions:send-expiry-reminders';
    protected $description = 'إرسال تذكير للطلاب الذين ستنتهي اشتراكاتهم قريباً';

    public function handle()
    {
        // الاشتراكات التي ستنتهي خلال 3 أيام
        $subscriptionsExpiringSoon = Subscription::where('is_active', true)
            ->where('status', 'approved')
            ->whereBetween('expires_at', [now(), now()->addDays(3)])
            ->with(['user', 'course'])
            ->get();

        foreach ($subscriptionsExpiringSoon as $subscription) {
            $daysRemaining = $subscription->getDaysRemaining();

            // هنا يمكنك إضافة إرسال إشعار أو بريد إلكتروني
            $this->info("تذكير: اشتراك {$subscription->user->name} في كورس {$subscription->course->title} سينتهي خلال {$daysRemaining} يوم");
        }

        // الاشتراكات التي انتهت اليوم
        $subscriptionsExpiredToday = Subscription::where('is_active', true)
            ->where('status', 'approved')
            ->whereDate('expires_at', now()->toDateString())
            ->with(['user', 'course'])
            ->get();

        foreach ($subscriptionsExpiredToday as $subscription) {
            $this->warn("انتهاء: اشتراك {$subscription->user->name} في كورس {$subscription->course->title} انتهى اليوم");

            // إلغاء تفعيل الاشتراك المنتهي
            $subscription->update(['is_active' => false]);
        }

        $this->info('تم إرسال جميع التذكيرات بنجاح');
    }
}
