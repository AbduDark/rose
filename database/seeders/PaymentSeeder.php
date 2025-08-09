
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Course;
use App\Models\Payment;

class PaymentSeeder extends Seeder
{
    public function run()
    {
        // Create sample payments for testing
        $users = User::where('role', 'user')->take(5)->get();
        $courses = Course::take(3)->get();

        if ($users->isEmpty() || $courses->isEmpty()) {
            $this->command->info('No users or courses found. Please run UserSeeder and CourseSeeder first.');
            return;
        }

        foreach ($users as $user) {
            foreach ($courses->take(2) as $course) {
                Payment::create([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'amount' => $course->price,
                    'currency' => 'EGP',
                    'payment_method' => 'vodafone_cash',
                    'vodafone_number' => '01012345678',
                    'sender_number' => '01087654321',
                    'transaction_reference' => 'TXN_' . time() . '_' . rand(1000, 9999),
                    'status' => collect(['pending', 'approved', 'rejected'])->random(),
                    'payment_data' => json_encode([
                        'submitted_at' => now(),
                        'test_data' => true
                    ])
                ]);
            }
        }

        $this->command->info('Payment seeder completed!');
    }
}
