
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // إضافة فهارس لجدول الاشتراكات
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'is_active']);
            $table->index(['expires_at', 'is_active']);
            $table->index(['status', 'is_active']);
        });

        // إضافة فهارس لجدول الدورات
        Schema::table('courses', function (Blueprint $table) {
            $table->index(['grade']);
            $table->index(['created_at']);
        });

        // إضافة فهارس لجدول الدروس
        Schema::table('lessons', function (Blueprint $table) {
            $table->index(['course_id']);
            $table->index(['course_id', 'order']);
        });

        // إضافة فهارس لجدول التعليقات
        Schema::table('comments', function (Blueprint $table) {
            $table->index(['lesson_id', 'user_id']);
            $table->index(['lesson_id']);
            $table->index(['user_id']);
        });

        // إضافة فهارس لجدول المفضلة
        Schema::table('favorites', function (Blueprint $table) {
            $table->index(['user_id', 'course_id']);
            $table->index(['user_id']);
        });

        // إضافة فهارس لجدول التقييمات
        Schema::table('ratings', function (Blueprint $table) {
            $table->index(['course_id']);
            $table->index(['user_id']);
        });

        // إضافة فهارس لجدول المدفوعات
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['user_id']);
            $table->index(['status']);
            $table->index(['created_at']);
        });

        // إضافة فهارس لجدول الإشعارات
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id']);
            $table->index(['is_read']);
            $table->index(['created_at']);
        });
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status', 'is_active']);
            $table->dropIndex(['expires_at', 'is_active']);
            $table->dropIndex(['status', 'is_active']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['grade']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['course_id']);
            $table->dropIndex(['course_id', 'order']);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['lesson_id', 'user_id']);
            $table->dropIndex(['lesson_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'course_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->dropIndex(['course_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['is_read']);
            $table->dropIndex(['created_at']);
        });
    }
};
