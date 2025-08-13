<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    public function up(): void
    {
        // التأكد من وجود عمود course_id إذا لم يكن موجوداً
        if (!Schema::hasColumn('comments', 'course_id')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->foreignId('course_id')->after('lesson_id')->constrained('courses')->onDelete('cascade');
                $table->index(['lesson_id', 'course_id']);
            });
        }

        // إضافة فهارس إضافية لتحسين الأداء
        Schema::table('comments', function (Blueprint $table) {
            if (!$this->indexExists('comments', 'comments_user_id_index')) {
                $table->index('user_id');
            }
            if (!$this->indexExists('comments', 'comments_is_approved_index')) {
                $table->index('is_approved');
            }
            if (!$this->indexExists('comments', 'comments_created_at_index')) {
                $table->index('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            // حذف الفهارس المضافة
            $table->dropIndex(['user_id']);
            $table->dropIndex(['is_approved']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['lesson_id', 'course_id']);

            // حذف عمود course_id إذا كان مضاف بواسطة هذا Migration
            if (Schema::hasColumn('comments', 'course_id')) {
                $table->dropForeign(['course_id']);
                $table->dropColumn('course_id');
            }
        });
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->pluck('Key_name')
            ->contains($index);
    }
};
