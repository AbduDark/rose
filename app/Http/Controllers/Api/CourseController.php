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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Models\Subscription; // Import Subscription model
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
            $user = auth()->user();

            // التحقق من وجود المستخدم
            if (!$user) {
                return $this->errorResponse(__('messages.auth.unauthenticated'), 401);
            }

            $course = Course::with(['lessons' => function($query) use ($user) {
                // إذا كان المستخدم مسجل دخول، فلتر الدروس حسب الجنس
                $query->orderBy('order')
                      ->where(function($q) use ($user) {
                          $q->where('target_gender', 'both')
                            ->orWhere('target_gender', $user->gender);
                      });
            }, 'ratings.user'])->findOrFail($id);

            // Check if course is active
            if (!$course->is_active) {
                return $this->errorResponse([
                    'ar' => 'هذا الكورس غير متاح حالياً',
                    'en' => 'This course is not available'
                ], 404);
            }

            $course->average_rating = $course->averageRating();
            $course->total_ratings = $course->totalRatings();

            // Add user-specific data if authenticated
            if ($request->user()) {
                $course->is_subscribed = $request->user()->isSubscribedTo($id);
                $course->is_favorited = $request->user()->hasFavorited($id);

                // للمستخدمين المسجلين، تحقق من قيود الجنس للكورس
                // This check is now handled at the lesson level
                // if ($course->target_gender !== 'both') {
                //     $userGender = $request->user()->gender;
                //     if ($course->target_gender !== $userGender) {
                //         return $this->errorResponse([
                //             'ar' => 'هذا الكورس غير متاح لجنسك',
                //             'en' => 'This course is not available for your gender'
                //         ], 403);
                //     }
                // }
            } else {
                $course->is_subscribed = false;
                $course->is_favorited = false;

                // للضيوف، إخفاء الكورسات المخصصة لجنس معين
                // This check is now handled at the lesson level
                // if ($course->target_gender !== 'both') {
                //     return $this->errorResponse([
                //         'ar' => 'يجب تسجيل الدخول لعرض هذا الكورس',
                //         'en' => 'Login required to view this course'
                //     ], 401);
                // }
            }

            return $this->successResponse(new CourseResource($course), [
                'ar' => 'تم جلب بيانات الكورس بنجاح',
                'en' => 'Course retrieved successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'الكورس غير موجود',
                'en' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Course show error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'level' => 'required|in:beginner,intermediate,advanced',
                'target_gender' => 'required|in:male,female,both',
                'duration_hours' => 'nullable|integer|min:0',
                'requirements' => 'nullable|string',
                'grade' => 'required|in:الاول,الثاني,الثالث',
                'image' => 'nullable|image|max:2048',
            ], [
                'title.required' => 'عنوان الكورس مطلوب|Course title is required',
                'description.required' => 'وصف الكورس مطلوب|Course description is required',
                'price.required' => 'سعر الكورس مطلوب|Course price is required',
                'price.numeric' => 'السعر يجب أن يكون رقم|Price must be a number',
                'level.required' => 'مستوى الكورس مطلوب|Course level is required',
                'target_gender.required' => 'الجنس المستهدف مطلوب|Target gender is required',
                'grade.required' => 'الصف الدراسي مطلوب|Grade is required',
                'image.image' => 'الملف المرفوع يجب أن يكون صورة|Uploaded file must be an image',
                'image.max' => 'حجم الصورة يجب ألا يزيد عن 2 ميجابايت|Image size must not exceed 2MB'
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

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

            return $this->successResponse(new CourseResource($course), [
                'ar' => 'تم إنشاء الكورس بنجاح',
                'en' => 'Course created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Course creation error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $course = Course::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'price' => 'sometimes|numeric|min:0',
                'level' => 'sometimes|in:beginner,intermediate,advanced',
                'duration_hours' => 'nullable|integer|min:0',
                'requirements' => 'nullable|string',
                'grade' => 'sometimes|in:الاول,الثاني,الثالث',
                'image' => 'nullable|image|max:2048',
                'is_active' => 'sometimes|boolean',
            ], [
                'title.string' => 'عنوان الكورس يجب أن يكون نص|Course title must be a string',
                'price.numeric' => 'السعر يجب أن يكون رقم|Price must be a number',
                'image.image' => 'الملف المرفوع يجب أن يكون صورة|Uploaded file must be an image',
                'image.max' => 'حجم الصورة يجب ألا يزيد عن 2 ميجابايت|Image size must not exceed 2MB'
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            $data = $request->all();

            if ($request->hasFile('image')) {
                if ($course->image) {
                    Storage::disk('public')->delete($course->image);
                }
                $data['image'] = $request->file('image')->store('courses', 'public');
            }

            $course->update($data);

            return $this->successResponse(new CourseResource($course), [
                'ar' => 'تم تحديث الكورس بنجاح',
                'en' => 'Course updated successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'الكورس غير موجود',
                'en' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Course update error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    public function destroy($id)
    {
        try {
            $course = Course::findOrFail($id);

            if ($course->image) {
                Storage::disk('public')->delete($course->image);
            }

            $course->delete();

            return $this->successResponse([], [
                'ar' => 'تم حذف الكورس بنجاح',
                'en' => 'Course deleted successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'الكورس غير موجود',
                'en' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Course deletion error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }
}