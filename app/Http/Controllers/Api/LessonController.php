<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use App\Models\Lesson;
use App\Models\Course;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator; // Import Validator facade
use Illuminate\Support\Facades\File; // Import File facade

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

        // Modified to select a random image from the available options without text generation
        foreach ($lessons as $lesson) {
            $imageOptions = ['image1.jpg', 'image2.jpg', 'image3.jpg']; // Assuming these are your image files
            $lesson->image_url = asset('images/' . $imageOptions[array_rand($imageOptions)]);
            $lesson->text_on_image = null; // Explicitly set text_on_image to null
        }

        return response()->json($lessons);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video' => 'required|file|mimes:mp4,avi,mov,wmv,flv|max:102400', // 100MB max
            'order' => 'nullable|integer|min:1',
            'duration_minutes' => 'nullable|integer|min:0',
            'is_free' => 'sometimes|boolean',
            'target_gender' => 'sometimes|in:male,female,both',
        ], [
            'title.required' => 'العنوان مطلوب|Title is required',
            'video.required' => 'ملف الفيديو مطلوب|Video file is required',
            'video.file' => 'يجب أن يكون ملف فيديو|Must be a video file',
            'video.mimes' => 'نوع الفيديو يجب أن يكون mp4, avi, mov, wmv, أو flv|Video must be mp4, avi, mov, wmv, or flv',
            'video.max' => 'حجم الفيديو يجب ألا يزيد عن 100 ميجابايت|Video size must not exceed 100MB',
            'order.integer' => 'ترتيب الدرس يجب أن يكون رقم|Order must be a number',
            'order.min' => 'ترتيب الدرس يجب أن يكون أكبر من صفر|Order must be greater than zero',
            'target_gender.in' => 'النوع المستهدف يجب أن يكون male أو female أو both|Target gender must be male, female or both'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), 422);
        }

        $data = $request->except(['course_id', 'video']);
        $data['course_id'] = $request->input('course_id');

        // Handle video upload
        if ($request->hasFile('video')) {
            $video = $request->file('video');
            // Generate a unique name for the video file
            $videoName = time() . '_' . $video->getClientOriginalName();
            // Ensure the directory exists
            $lessonsDir = public_path('lessons');
            if (!File::isDirectory($lessonsDir)) {
                File::makeDirectory($lessonsDir, 0755, true, true);
            }
            // Move video to public/lessons directory
            $video->move($lessonsDir, $videoName);
            $data['video_url'] = url('lessons/' . $videoName);
        } else {
            // If no video is uploaded, but it's required, this case should ideally not be reached due to validation
            // However, as a fallback, we could return an error or set video_url to null if the validation allows it.
            // Based on the validation 'video' => 'required|file...', this else block might not be necessary.
            // If video_url should be settable via a URL directly when not uploading a file, the validation needs adjustment.
            // For now, assuming video upload is the primary mechanism.
            if ($request->filled('video_url')) {
                $data['video_url'] = $request->input('video_url');
            } else {
                 return $this->errorResponse('Video is required or a video URL must be provided.', 422);
            }
        }


        // Set default values
        $data['is_free'] = $request->boolean('is_free', false);
        $data['target_gender'] = $data['target_gender'] ?? 'both';

        // Set order if not provided
        if (!isset($data['order'])) {
            $lastOrder = Lesson::where('course_id', $data['course_id'])->max('order');
            $data['order'] = $lastOrder ? $lastOrder + 1 : 1;
        }

        $lesson = Lesson::create($data);

        return $this->successResponse($lesson, __('messages.lesson.created_successfully'), 201);
    }

    public function update(Request $request, $id)
    {
        $lesson = Lesson::findOrFail($id);

        $rules = [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'video' => 'nullable|file|mimes:mp4,avi,mov,wmv,flv|max:102400', // 100MB max
            'order' => 'nullable|integer|min:1',
            'duration_minutes' => 'nullable|integer|min:0',
            'is_free' => 'sometimes|boolean',
            'target_gender' => 'sometimes|in:male,female,both',
        ];

        $messages = [
            'title.required' => 'العنوان مطلوب|Title is required',
            'video.file' => 'يجب أن يكون ملف فيديو|Must be a video file',
            'video.mimes' => 'نوع الفيديو يجب أن يكون mp4, avi, mov, wmv, أو flv|Video must be mp4, avi, mov, wmv, or flv',
            'video.max' => 'حجم الفيديو يجب ألا يزيد عن 100 ميجابايت|Video size must not exceed 100MB',
            'order.integer' => 'ترتيب الدرس يجب أن يكون رقم|Order must be a number',
            'order.min' => 'ترتيب الدرس يجب أن يكون أكبر من صفر|Order must be greater than zero',
            'target_gender.in' => 'النوع المستهدف يجب أن يكون male أو female أو both|Target gender must be male, female or both'
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), 422);
        }

        $data = $request->except(['video']);

        // Handle video upload
        if ($request->hasFile('video')) {
            $video = $request->file('video');
            $videoName = time() . '_' . $video->getClientOriginalName();

            // Create lessons directory if it doesn't exist
            $lessonsDir = public_path('lessons');
            if (!File::isDirectory($lessonsDir)) {
                File::makeDirectory($lessonsDir, 0755, true, true);
            }

            // Move video to public/lessons directory
            $video->move($lessonsDir, $videoName);
            $data['video_url'] = url('lessons/' . $videoName);

            // Optional: Delete the old video if it exists and a new one is uploaded
            if ($lesson->video_url && str_contains($lesson->video_url, 'lessons/')) {
                $oldVideoPath = public_path(str_replace(url('/'), '', $lesson->video_url));
                if (File::exists($oldVideoPath)) {
                    File::delete($oldVideoPath);
                }
            }
        } elseif ($request->filled('video_url')) {
            // If video_url is provided and no file is uploaded, update with the provided URL
            $data['video_url'] = $request->input('video_url');
        } elseif ($request->has('video_url') && $request->input('video_url') === null) {
            // If video_url is explicitly set to null, clear it
             $data['video_url'] = null;
             // Optional: Delete the old video if it exists and video_url is being cleared
             if ($lesson->video_url && str_contains($lesson->video_url, 'lessons/')) {
                $oldVideoPath = public_path(str_replace(url('/'), '', $lesson->video_url));
                if (File::exists($oldVideoPath)) {
                    File::delete($oldVideoPath);
                }
            }
        }


        $lesson->update($data);

        return $this->successResponse($lesson, __('messages.lesson.updated_successfully'));
    }

    public function destroy($id)
    {
        $lesson = Lesson::findOrFail($id);

        // Optional: Delete the video file if it exists
        if ($lesson->video_url && str_contains($lesson->video_url, 'lessons/')) {
            $videoPath = public_path(str_replace(url('/'), '', $lesson->video_url));
            if (File::exists($videoPath)) {
                File::delete($videoPath);
            }
        }

        $lesson->delete();

        return $this->successResponse([], __('messages.lesson.deleted_successfully'));
    }

    public function show($id)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->errorResponse(__('messages.auth.unauthenticated'), 401);
            }

            $lesson = Lesson::with(['course', 'comments.user'])->find($id);

            if (!$lesson) {
                return $this->errorResponse(__('messages.lesson.not_found'), 404);
            }

            // التحقق من توافق الجنس
            if ($lesson->target_gender !== 'both' && $lesson->target_gender !== $user->gender) {
                return $this->errorResponse(__('messages.lesson.gender_not_allowed'), 403);
            }

            // التحقق من حالة الكورس
            if (!$lesson->course->is_active) {
                return $this->errorResponse(__('messages.course.not_active'), 403);
            }

            // التحقق من الاشتراك إذا لم يكن الدرس مجاني
            if (!$lesson->is_free) {
                $subscription = Subscription::where('user_id', $user->id)
                    ->where('course_id', $lesson->course_id)
                    ->where('is_active', true)
                    ->where('is_approved', true)
                    ->first();

                if (!$subscription) {
                    return $this->errorResponse(__('messages.subscription.required'), 403);
                }
            }

            // If the subscription is active but has expired based on duration, prompt for renewal
            if ($lesson->is_free == false && $subscription) {
                $subscriptionEndDate = $subscription->created_at->addDays(30); // Assuming subscription is for 30 days
                if (now()->gt($subscriptionEndDate)) {
                    return $this->errorResponse(__('messages.subscription.expired_prompt_renewal'), 403);
                }
            }


            return $this->successResponse($lesson, __('messages.lesson.retrieved_successfully'));

        } catch (\Exception $e) {
            Log::error('Error retrieving lesson: ' . $e->getMessage());
            return $this->errorResponse(__('messages.general.server_error'), 500);
        }
    }

    /**
     * Get all subscriptions with pagination and user details.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllSubscriptions(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10); // Default to 10 items per page
            $subscriptions = Subscription::with(['user', 'course'])
                ->where('is_approved', true) // Assuming only approved subscriptions are relevant
                ->paginate($perPage);

            return $this->successResponse($subscriptions, __('messages.subscription.retrieved_successfully'));

        } catch (\Exception $e) {
            Log::error('Error retrieving all subscriptions: ' . $e->getMessage());
            return $this->errorResponse(__('messages.general.server_error'), 500);
        }
    }
}