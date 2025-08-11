
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('vodafone_number')->after('course_id');
            $table->string('parent_phone')->after('vodafone_number');
            $table->text('student_info')->nullable()->after('parent_phone');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('student_info');
            $table->text('admin_notes')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('admin_notes');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->unsignedBigInteger('approved_by')->nullable()->after('rejected_at');
            $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_by');
            
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn([
                'vodafone_number',
                'parent_phone', 
                'student_info',
                'status',
                'admin_notes',
                'approved_at',
                'rejected_at',
                'approved_by',
                'rejected_by'
            ]);
        });
    }
};
