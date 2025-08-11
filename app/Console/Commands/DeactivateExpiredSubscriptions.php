
<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class DeactivateExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:deactivate-expired';
    protected $description = 'تعطيل الاشتراكات المنتهية الصلاحية تلقائياً';

    public function handle()
    {
        $this->info('بدء عملية تعطيل الاشتراكات المنتهية الصلاحية...');

        // العثور على الاشتراكات المنتهية الصلاحية والنشطة
        $expiredSubscriptions = Subscription::where('is_active', true)
            ->where('status', 'approved')
            ->where('expires_at', '<', now())
            ->get();

        $this->info("تم العثور على {$expiredSubscriptions->count()} اشتراك منتهي الصلاحية");

        $deactivatedCount = 0;

        foreach ($expiredSubscriptions as $subscription) {
            $daysExpired = now()->diffInDays($subscription->expires_at);
            
            $subscription->update(['is_active' => false]);
            
            $this->line("تم تعطيل اشتراك المستخدم {$subscription->user->name} في كورس {$subscription->course->title} (منتهي منذ {$daysExpired} يوم)");
            
            $deactivatedCount++;
        }

        $this->info("تم تعطيل {$deactivatedCount} اشتراك منتهي الصلاحية بنجاح.");
        
        return 0;
    }
}
