<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class DeactivateExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:deactivate-expired';
    protected $description = 'تعطيل الاشتراكات المنتهية الصلاحية تلقائياً وإرسال إشعارات';

    public function handle()
    {
        $this->info('بدء عملية تعطيل الاشتراكات المنتهية الصلاحية وإرسال الإشعارات...');
        Log::info('Starting deactivation of expired subscriptions');

        // الحصول على الاشتراكات المنتهية الصلاحية
        $expiredSubscriptions = Subscription::with(['user', 'course'])
            ->where('is_active', true)
            ->where('status', 'approved')
            ->where('expires_at', '<', now())
            ->get();

        $this->info("تم العثور على {$expiredSubscriptions->count()} اشتراك منتهي الصلاحية");
        Log::info("Found {$expiredSubscriptions->count()} expired subscriptions");

        // معالجة كل اشتراك
        $deactivatedCount = 0;
        foreach ($expiredSubscriptions as $subscription) {
            try {
                // إرسال إشعار للمستخدم
                NotificationService::subscriptionExpired(
                    $subscription->user_id,
                    $subscription->course_id
                );

                $this->line("تم إرسال إشعار للمستخدم {$subscription->user->name} بانتهاء اشتراكه في كورس {$subscription->course->title}");

                // تعطيل الاشتراك
                $subscription->update(['is_active' => false]);
                $deactivatedCount++;

                Log::info("Deactivated subscription and sent notification", [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'course_id' => $subscription->course_id,
                    'expired_at' => $subscription->expires_at
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to process subscription {$subscription->id}: " . $e->getMessage());
                $this->error("خطأ في معالجة الاشتراك {$subscription->id}: " . $e->getMessage());
            }
        }

        $this->info("تم تعطيل {$deactivatedCount} اشتراك منتهي الصلاحية بنجاح.");
        Log::info("Successfully deactivated {$deactivatedCount} subscriptions");

        return 0;
    }
}
