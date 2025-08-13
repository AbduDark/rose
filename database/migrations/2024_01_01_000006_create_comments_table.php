<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade'); // تمت الإضافة هنا
            $table->text('content');
            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            // إضافة فهرس مركب لتحسين الأداء
            $table->index(['lesson_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
