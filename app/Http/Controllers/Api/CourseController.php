<?php

namespace App\Http\Controllers\Api;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Storage, Cache, Log, Validator, Auth};
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use App\Models\{Course, Subscription, User};
use App\Http\Resources\CourseResource;
use App\Services\{CourseImageGenerator, ImageOptimizer};

class CourseController extends BaseController
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    public function index(Request $request)
    {
        try {
            $cacheKey = 'courses_' . md5(serialize($request->all()));

            $courses = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($request) {
                $query = Course::where('is_active', true)
                    ->with(['ratings'])
                    ->withCount('lessons');

                // Search
                if ($request->has('search')) {
                    $query->where(function($q) use ($request) {
                        $q->where('title', 'like', "%{$request->search}%")
                          ->orWhere('description', 'like', "%{$request->search}%")
                          ->orWhere('instructor_name', 'like', "%{$request->search}%");
                    });
                }

                // Filters
                $query->when($request->level, fn($q, $level) => $q->where('level', $level))
                     ->when($request->language, fn($q, $lang) => $q->where('language', $lang))
                     ->when($request->grade, fn($q, $grade) => $q->where('grade', $grade))
                     ->when($request->min_price, fn($q, $min) => $q->where('price', '>=', $min))
                     ->when($request->max_price, fn($q, $max) => $q->where('price', '<=', $max));

                // Sorting
                $sortBy = in_array($request->sort_by, ['title', 'price', 'created_at', 'duration_hours'])
                    ? $request->sort_by
                    : 'created_at';

                $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

                return $query->orderBy($sortBy, $sortOrder)
                           ->paginate($request->per_page ?? 10);
            });

            return $this->successResponse(
                CourseResource::collection($courses),
                [
                    'ar' => 'تم جلب الكورسات بنجاح',
                    'en' => 'Courses retrieved successfully'
                ]
            );

        } catch (\Exception $e) {
            Log::error('Courses index error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->serverErrorResponse();
        }
    }

    public function show($id, Request $request)
    {
        try {
            $user = $request->user();
            $course = Course::with(['ratings.user'])
                          ->findOrFail($id);

            if (!$course->is_active) {
                return $this->errorResponse([
                    'ar' => 'الكورس غير متاح حالياً',
                    'en' => 'Course not available'
                ], 404);
            }

            $course->load(['lessons' => function($query) use ($user, $course) {
                $query->orderBy('order');

                if ($user) {
                    if ($user->isSubscribedTo($course->id)) {
                        $query->where(function($q) use ($user) {
                            $q->where('target_gender', 'both')
                              ->orWhere('target_gender', $user->gender);
                        });
                    } else {
                        $query->where('is_free', true)
                              ->where(function($q) use ($user) {
                                  $q->where('target_gender', 'both')
                                    ->orWhere('target_gender', $user->gender);
                              });
                    }
                } else {
                    $query->where('is_free', true)
                          ->where('target_gender', 'both');
                }
            }]);

            $course->average_rating = $course->averageRating();
            $course->total_ratings = $course->totalRatings();
            $course->is_subscribed = $user ? $user->isSubscribedTo($id) : false;
            $course->is_favorited = $user ? $user->hasFavorited($id) : false;

            $subscriptionInfo = null;

            if ($user && !$user->isAdmin()) {
                $subscription = $user->subscriptions()
                    ->where('course_id', $id)
                    ->where('status', 'approved')
                    ->first();

                if ($subscription) {
                    $subscriptionInfo = [
                        'is_subscribed' => true,
                        'is_active' => $subscription->is_active,
                        'is_expired' => $subscription->isExpired(),
                        'expires_at' => $subscription->expires_at,
                        'days_remaining' => $subscription->getDaysRemaining(),
                        'hours_remaining' => $subscription->getHoursRemaining(),
                        'is_expiring_soon' => $subscription->isExpiringSoon(),
                        'subscription_id' => $subscription->id
                    ];
                } else {
                    $subscriptionInfo = [
                        'is_subscribed' => false,
                        'message' => [
                            'ar' => 'يجب الاشتراك في هذا الكورس للوصول إلى محتواه',
                            'en' => 'You must subscribe to this course to access its content'
                        ]
                    ];
                }
            }


            return $this->successResponse(
                new CourseResource($course),
                [
                    'ar' => 'تم جلب الكورس بنجاح',
                    'en' => 'Course retrieved successfully'
                ]
            );

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'الكورس غير موجود',
                'en' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Course show error', [
                'course_id' => $id,
                'user_id' => $user?->id ?? 'guest',
                'error' => $e->getMessage()
            ]);
            return $this->serverErrorResponse();
        }
    }

    public function store(Request $request)
    {
        try {
            // التحقق من أن المستخدم أدمن
            if (!$request->user() || !$request->user()->isAdmin()) {
                return $this->errorResponse([
                    'ar' => 'غير مصرح لك بإضافة كورس',
                    'en' => 'Unauthorized to create course'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255|unique:courses',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'level' => 'required|in:beginner,intermediate,advanced',
                'duration_hours' => 'nullable|integer|min:0',
                'requirements' => 'nullable|string',
                'instructor_name' => 'nullable|string|max:255',
                'language' => 'nullable|string|max:10',
                'grade' => 'required|in:الاول,الثاني,الثالث',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            ], [
                'title.required' => 'العنوان مطلوب|Title is required',
                'title.unique' => 'العنوان موجود بالفعل|Title already exists',
                'description.required' => 'الوصف مطلوب|Description is required',
                'price.required' => 'السعر مطلوب|Price is required',
                'price.numeric' => 'السعر يجب أن يكون رقم|Price must be numeric',
                'level.required' => 'المستوى مطلوب|Level is required',
                'level.in' => 'المستوى يجب أن يكون beginner أو intermediate أو advanced|Invalid level',
                'grade.required' => 'الصف الدراسي مطلوب|Grade is required',
                'grade.in' => 'الصف الدراسي يجب أن يكون الاول أو الثاني أو الثالث|Invalid grade',
                'image.image' => 'الملف يجب أن يكون صورة|File must be an image',
                'image.mimes' => 'الصورة يجب أن تكون jpeg أو png أو jpg|Image must be jpeg, png or jpg',
                'image.max' => 'حجم الصورة يجب ألا يزيد عن 2 ميجابايت|Image size must not exceed 2MB'
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

     $data = $request->except('image');
        $data['language'] = $data['language'] ?? 'ar';
        $data['instructor_name'] = $data['instructor_name'] ?? 'أكاديمية روز';

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();
            $relativePath = 'uploads/courses/' . $filename;
            $image->move(public_path('uploads/courses'), $filename);
            $data['image'] = $relativePath;
        } else {
            $imageGenerator = new CourseImageGenerator();
            $generatedImagePath = $imageGenerator->generateCourseImage(
                $request->title,
                floatval($request->price),
                $request->description,
                $request->grade
            );
            $data['image'] = $generatedImagePath;
        }

        $course = Course::create($data);
        Cache::forget('courses_' . md5(''));

        // إنشاء URL كامل للصورة
        $course->image_url = url($course->image);

        return $this->successResponse(
            new CourseResource($course),
            [
                'ar' => 'تم إنشاء الكورس بنجاح',
                'en' => 'Course created successfully'
            ],
            201
        );
        } catch (\Exception $e) {
            Log::error('Course creation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->except('image')
            ]);
            return $this->serverErrorResponse();
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $course = Course::findOrFail($id);

            if (!$request->user() || !$request->user()->isAdmin()) {
                return $this->errorResponse([
                    'ar' => 'غير مصرح لك بتعديل هذا الكورس',
                    'en' => 'Unauthorized to update this course'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255|unique:courses,title,' . $id,
                'description' => 'sometimes|string',
                'price' => 'sometimes|numeric|min:0',
                'level' => 'sometimes|in:beginner,intermediate,advanced',
                'duration_hours' => 'nullable|integer|min:0',
                'requirements' => 'nullable|string',
                'instructor_name' => 'sometimes|string|max:255',
                'language' => 'sometimes|string|max:10',
                'grade' => 'sometimes|in:الاول,الثاني,الثالث',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            $data = $request->except('image');

            if ($request->hasFile('image')) {
                // حذف الصورة القديمة
                if ($course->image && Storage::disk('public')->exists($course->image)) {
                    Storage::disk('public')->delete($course->image);
                }

                $image = $request->file('image');
                $path = $image->store('courses', 'public');
                $data['image'] = $path;
            }

            $course->update($data);

            // مسح الكاش
            Cache::forget('courses_' . md5(''));  // مسح كاش القائمة
            Cache::forget('course_' . $id);       // مسح كاش الكورس المحدد

            return $this->successResponse(
                new CourseResource($course->fresh()),
                [
                    'ar' => 'تم تحديث الكورس بنجاح',
                    'en' => 'Course updated successfully'
                ]
            );

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'الكورس غير موجود',
                'en' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Course update error', [
                'course_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->serverErrorResponse();
        }
    }

    public function destroy($id)
    {
        try {
            $course = Course::findOrFail($id);

            if (!request()->user() || !request()->user()->isAdmin()) {
                return $this->errorResponse([
                    'ar' => 'غير مصرح لك بحذف هذا الكورس',
                    'en' => 'Unauthorized to delete this course'
                ], 403);
            }

            if ($course->image && Storage::disk('public')->exists($course->image)) {
                Storage::disk('public')->delete($course->image);
            }

            $course->delete();
            Cache::forget('courses_' . md5(''));  // مسح كاش القائمة
            Cache::forget('course_' . $id);       // مسح كاش الكورس المحذوف

            return $this->successResponse(
                null,
                [
                    'ar' => 'تم حذف الكورس بنجاح',
                    'en' => 'Course deleted successfully'
                ]
            );

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'الكورس غير موجود',
                'en' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Course deletion error', [
                'course_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->serverErrorResponse();
        }
    }
}