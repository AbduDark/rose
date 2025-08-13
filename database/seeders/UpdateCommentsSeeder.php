<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Lesson;
use Illuminate\Database\Seeder;

class UpdateCommentsSeeder extends Seeder
{
    public function run()
    {
        $comments = Comment::whereNull('course_id')->get();

        foreach ($comments as $comment) {
            $lesson = Lesson::find($comment->lesson_id);
            if ($lesson) {
                $comment->update([
                    'course_id' => $lesson->course_id
                ]);
            }
        }
    }
}
