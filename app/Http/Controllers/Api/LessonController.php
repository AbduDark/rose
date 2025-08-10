<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use App\Models\Lesson;
use App\Models\Course;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    use ApiResponseTrait;
    public function index($courseId, Request $request)
    {
        $course = Course::findOrFail($courseId);
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'يجب تسجيل الدخول لعرض الدروس'], 401);
        }

        $isSubscribed = $user->isSubscribedTo($courseId);

        if (!$isSubscribed) {
            return response()->json(['message' => 'يجب الاشتراك في الدورة أولاً'], 403);
        }

        $userGender = $user->gender;
        $lessons = $course->lessons()
            ->where(function($query) use ($userGender) {
                $query->where('target_gender', 'both')
                      ->orWhere('target_gender', $userGender);
            })
            ->orderBy('order')
            ->get();

        return response()->json($lessons);
    }

    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'video_url' => 'nullable|url',
            'order' => 'nullable|integer|min:0',
            'duration_minutes' => 'nullable|integer|min:0',
            'is_free' => 'boolean',
            'target_gender' => 'required|in:male,female,both',
        ]);

        $lesson = Lesson::create($request->all());

        return response()->json($lesson, 201);
    }

    public function update(Request $request, $id)
    {
        $lesson = Lesson::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'content' => 'sometimes|string',
            'video_url' => 'nullable|url',
            'order' => 'nullable|integer|min:0',
            'duration_minutes' => 'nullable|integer|min:0',
            'is_free' => 'boolean',
            'target_gender' => 'sometimes|in:male,female,both',
        ]);

        $lesson->update($request->all());

        return response()->json($lesson);
    }

    public function destroy($id)
    {
        $lesson = Lesson::findOrFail($id);
        $lesson->delete();

        return response()->json(['message' => 'Lesson deleted successfully']);
    }
}
