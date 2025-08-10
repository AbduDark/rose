<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\CourseResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use App\Services\CourseImageGenerator; // Assuming this service exists for image generation
use Illuminate\Support\Facades\Cache;
class CourseController extends Controller
{
    use ApiResponseTrait;
    public function index(Request $request)
    {
        try {
            // Create cache key based on request parameters
            $cacheKey = 'courses_' . md5(serialize($request->all()));

            // Cache for 30 minutes
            $courses = Cache::remember($cacheKey, 1800, function () use ($request) {
                $query = Course::query()->where('is_active', true);

                // Search functionality
                if ($request->has('search')) {
                    $search = $request->get('search');
                    $query->where(function($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%")
                          ->orWhere('instructor_name', 'like', "%{$search}%");
                    });
                }

                // Filter by level
                if ($request->has('level')) {
                    $query->where('level', $request->get('level'));
                }

                // Filter by language
                if ($request->has('language')) {
                    $query->where('language', $request->get('language'));
                }

                // Filter by price range
                if ($request->has('min_price')) {
                    $query->where('price', '>=', $request->get('min_price'));
                }
                if ($request->has('max_price')) {
                    $query->where('price', '<=', $request->get('max_price'));
                }

                // Sort options
                $sortBy = $request->get('sort_by', 'created_at');
                $sortOrder = $request->get('sort_order', 'desc');

                if (in_array($sortBy, ['title', 'price', 'created_at', 'duration_hours'])) {
                    $query->orderBy($sortBy, $sortOrder);
                }

                return $query->with(['ratings'])
                           ->withCount('lessons')
                           ->paginate($request->get('per_page', 10));
            });

            return $this->successResponse(CourseResource::collection($courses), [
                'ar' => 'تم جلب الكورسات بنجاح',
                'en' => 'Courses retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Courses index error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    public function show($id, Request $request)
    {
        try {
            $course = Course::with(['lessons', 'ratings.user'])->findOrFail($id);

            // Check if course is active
            if (!$course->is_active) {
                return $this->errorResponse([
                    'ar' => 'هذا الكورس غير متاح حالياً',
                    'en' => 'This course is not available'
                ], 404);
            }

            // Check gender access
            if ($request->user()) {
                $userGender = $request->user()->gender;
                if ($course->target_gender !== 'both' && $course->target_gender !== $userGender) {
                    return $this->errorResponse([
                        'ar' => 'هذا الكورس غير متاح لجنسك',
                        'en' => 'This course is not available for your gender'
                    ], 403);
                }
            } elseif ($course->target_gender !== 'both') {
                return $this->errorResponse([
                    'ar' => 'يجب تسجيل الدخول لعرض هذا الكورس',
                    'en' => 'Login required to view this course'
                ], 401);
            }

            $course->average_rating = $course->averageRating();
            $course->total_ratings = $course->totalRatings();

            if ($request->user()) {
                $course->is_subscribed = $request->user()->isSubscribedTo($id);
                $course->is_favorited = $request->user()->hasFavorited($id);
            }

            return $this->successResponse(new CourseResource($course), [
                'ar' => 'تم جلب بيانات الكورس بنجاح',
                'en' => 'Course retrieved successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Course');
        } catch (\Exception $e) {
            Log::error('Course show error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'level' => 'required|in:beginner,intermediate,advanced',
            'target_gender' => 'required|in:male,female,both',
            'duration_hours' => 'nullable|integer|min:0',
            'requirements' => 'nullable|string',
            'grade' => 'required|in:الاول,الثاني,الثالث',
            'image' => 'nullable|image|max:2048',
        ]);

        $data = $request->all();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('courses', 'public');
        } else {
            // توليد صورة تلقائية
            $imageGenerator = new CourseImageGenerator();
            $data['image'] = $imageGenerator->generateCourseImage(
                $request->title,
                $request->price,
                $request->description,
                $request->grade
            );
        }

        $course = Course::create($data);

        return response()->json($course, 201);
    }

    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'level' => 'sometimes|in:beginner,intermediate,advanced',
            'duration_hours' => 'nullable|integer|min:0',
            'requirements' => 'nullable|string',
            'grade' => 'sometimes|in:الاول,الثاني,الثالث',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = $request->all();

        if ($request->hasFile('image')) {
            if ($course->image) {
                Storage::disk('public')->delete($course->image);
            }
            $data['image'] = $request->file('image')->store('courses', 'public');
        }

        $course->update($data);

        return response()->json($course);
    }

    public function destroy($id)
    {
        $course = Course::findOrFail($id);

        if ($course->image) {
            Storage::disk('public')->delete($course->image);
        }

        $course->delete();

        return response()->json(['message' => 'Course deleted successfully']);
    }
}
