
<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class DeactivateExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:deactivate-expired';
    protected $description = 'Deactivate expired subscriptions automatically';

    public function handle()
    {
        $expiredCount = Subscription::where('is_active', true)
            ->where('status', 'approved')
            ->where('expires_at', '<', now())
            ->update(['is_active' => false]);

        $this->info("تم تعطيل {$expiredCount} اشتراك منتهي الصلاحية.");
        
        return 0;
    }
}
