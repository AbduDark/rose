<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'is_active']);
            $table->index(['expires_at', 'is_active']);
            $table->index(['status', 'is_active']);
        });

        // إضافة فهارس لجدول الدورات
        Schema::table('courses', function (Blueprint $table) {
            $table->index(['grade']);
            $table->index(['is_active']);
            $table->index(['created_at']);
        });

        // إضافة فهارس لجدول الدروس
        Schema::table('lessons', function (Blueprint $table) {
            $table->index(['course_id']);
            $table->index(['course_id', 'order']);
            $table->index(['is_active']);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->index(['lesson_id', 'user_id']);
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
            $table->dropIndex(['is_active']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['course_id']);
            $table->dropIndex(['course_id', 'order']);
            $table->dropIndex(['is_active']);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['lesson_id', 'user_id']);
        });
    }
};