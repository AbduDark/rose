<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use App\Services\NotificationService; // افتراض أن لديك خدمة إشعارات

class DeactivateExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:deactivate-expired';
    protected $description = 'تعطيل الاشتراكات المنتهية الصلاحية تلقائياً وإرسال إشعارات';

    public function handle()
    {
        $this->info('بدء عملية تعطيل الاشتراكات المنتهية الصلاحية وإرسال الإشعارات...');

        // الحصول على الاشتراكات المنتهية الصلاحية قبل تعطيلها
        $expiredSubscriptions = Subscription::where('is_active', true)
            ->where('status', 'approved')
            ->where('expires_at', '<', now())
            ->get();

        $this->info("تم العثور على {$expiredSubscriptions->count()} اشتراك منتهي الصلاحية");

        // إرسال إشعارات للمستخدمين
        foreach ($expiredSubscriptions as $subscription) {
            // افتراض أن NotificationService لديه دالة subscriptionExpired
            // هذه الدالة يجب أن تكون مسؤولة عن إرسال الإشعار للمستخدم المحدد
            NotificationService::subscriptionExpired($subscription->user_id, $subscription->course_id);
            $this->line("تم إرسال إشعار للمستخدم {$subscription->user->name} بانتهاء اشتراكه في كورس {$subscription->course->title}");
        }

        // تعطيل الاشتراكات
        $deactivatedCount = 0;
        foreach ($expiredSubscriptions as $subscription) {
            $subscription->update(['is_active' => false]);
            $deactivatedCount++;
        }

        $this->info("تم تعطيل {$deactivatedCount} اشتراك منتهي الصلاحية بنجاح.");

        return 0;
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class DeactivateExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:deactivate-expired';
    protected $description = 'Deactivate expired subscriptions';

    public function handle()
    {
        $expiredSubscriptions = Subscription::where('status', 'approved')
            ->where('expires_at', '<', now())
            ->where('is_active', true)
            ->get();

        $count = 0;
        foreach ($expiredSubscriptions as $subscription) {
            $subscription->update(['is_active' => false]);
            $count++;
            
            Log::info("Deactivated expired subscription", [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'course_id' => $subscription->course_id,
                'expired_at' => $subscription->expires_at
            ]);
        }

        $this->info("Deactivated {$count} expired subscriptions.");
        Log::info("Deactivated {$count} expired subscriptions via command.");

        return 0;
    }
}
