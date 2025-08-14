<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lesson;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;

class LessonVideoController extends Controller
{
    public function upload(Request $request, $lessonId)
    {
        $request->validate([
            'video' => 'required|mimes:mp4,mov,avi,wmv|max:204800' // 200MB
        ]);

        $lesson = Lesson::findOrFail($lessonId);

        // تحديد المسار داخل storage
        $fileName = Str::uuid() . '.' . $request->video->getClientOriginalExtension();
        $path = $request->video->storeAs(
            "private_videos/lessons/{$lesson->id}",
            $fileName
        );

        // حفظ المسار في قاعدة البيانات
        $lesson->update([
            'video_path' => $path
        ]);

        return response()->json([
            'message' => 'تم رفع الفيديو بنجاح، وجاري معالجته...',
            'path' => $path
        ]);

   }
public function getKey(Request $request, Lesson $lesson)
{
    /** @var User $user */
    // تحقق من المستخدم
    if (!auth()->check()) {
        abort(403, 'Unauthorized');
    }

    // تحقق إذا الطالب مشترك في الكورس
    if ($lesson->course->students()->where('user_id', auth()->id())->doesntExist()) {
        abort(403, 'You are not enrolled in this course');
    }

    $keyPath = storage_path("app/private_videos/hls/lesson_{$lesson->id}/enc.key");
    return response()->file($keyPath, [
        'Content-Type' => 'application/octet-stream'
    ]);
}
public function stream(Lesson $lesson)
{
    if (!auth()->check()) {
        abort(403, 'Unauthorized');
    }

    if ($lesson->course->students()->where('user_id', auth()->id())->doesntExist()) {
        abort(403, 'Not enrolled');
    }

    $path = storage_path("app/{$lesson->video_path}");
    return response()->file($path);
}

}
