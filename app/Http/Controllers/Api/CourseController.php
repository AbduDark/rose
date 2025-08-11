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

class CourseController extends Basecontroller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
        $this->middleware('can:create,course')->only('store');
        $this->middleware('can:update,course')->only('update');
        $this->middleware('can:delete,course')->only('destroy');
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
                __('messages.courses.retrieved')
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
                return $this->errorResponse(__('messages.courses.not_available'), 404);
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

            return $this->successResponse(
                new CourseResource($course),
                __('messages.courses.retrieved')
            );

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(__('messages.courses.not_found'), 404);
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
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255|unique:courses',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'level' => 'required|in:beginner,intermediate,advanced',
                'target_gender' => 'required|in:male,female,both',
                'duration_hours' => 'nullable|integer|min:0',
                'requirements' => 'nullable|string',
                'grade' => 'required|in:الاول,الثاني,الثالث',
                'image' => 'nullable|image|max:2048',
            ], [
                'title.required' => __('validation.courses.title_required'),
                'description.required' => __('validation.courses.description_required'),
                'price.required' => __('validation.courses.price_required'),
                'level.required' => __('validation.courses.level_required'),
                'target_gender.required' => __('validation.courses.gender_required'),
                'grade.required' => __('validation.courses.grade_required'),
                'image.image' => __('validation.courses.image_type'),
                'image.max' => __('validation.courses.image_size')
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            $data = $request->except('image');

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $path = $image->store('courses', 'public');

                // $imageService = new ImageOptimizer();
                // $imageService->optimize(storage_path('app/public/' . $path));

                $data['image'] = $path;
            } else {
                $imageGenerator = new CourseImageGenerator();
                $data['image'] = $imageGenerator->generateCourseImage(
                    $request->title,
                    $request->price,
                    $request->description,
                    $request->grade
                );
            }

            $data['user_id'] = $request->user()->id;
            $course = Course::create($data);

            Cache::tags('courses')->flush();

            return $this->successResponse(
                new CourseResource($course),
                __('messages.courses.created'),
                201
            );

        } catch (\Exception $e) {
            Log::error('Course creation error', [
                'error' => $e->getMessage(),
                'request' => $request->except('image')
            ]);
            return $this->serverErrorResponse();
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $course = Course::findOrFail($id);

            if ($request->user()->cannot('update', $course)) {
                return $this->errorResponse(__('messages.unauthorized'), 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255|unique:courses,title,' . $id,
                'description' => 'sometimes|string',
                'price' => 'sometimes|numeric|min:0',
                'level' => 'sometimes|in:beginner,intermediate,advanced',
                'duration_hours' => 'nullable|integer|min:0',
                'requirements' => 'nullable|string',
                'grade' => 'sometimes|in:الاول,الثاني,الثالث',
                'image' => 'nullable|image|max:2048',
                'is_active' => 'sometimes|boolean',
            ], [
                'title.string' => __('validation.courses.title_string'),
                'price.numeric' => __('validation.courses.price_numeric'),
                'image.image' => __('validation.courses.image_type'),
                'image.max' => __('validation.courses.image_size')
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse(new ValidationException($validator));
            }

            $data = $request->except('image');

            if ($request->hasFile('image')) {
                if ($course->image && Storage::disk('public')->exists($course->image)) {
                    Storage::disk('public')->delete($course->image);
                }

                $image = $request->file('image');
                $path = $image->store('courses', 'public');

                // $imageService = new ImageOptimizer();
                // $imageService->optimize(storage_path('app/public/' . $path));

                $data['image'] = $path;
            }

            $course->update($data);
            Cache::tags('courses')->flush();

            return $this->successResponse(
                new CourseResource($course),
                __('messages.courses.updated')
            );

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(__('messages.courses.not_found'), 404);
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

            if (request()->user()->cannot('delete', $course)) {
                return $this->errorResponse(__('messages.unauthorized'), 403);
            }

            if ($course->image && Storage::disk('public')->exists($course->image)) {
                Storage::disk('public')->delete($course->image);
            }

            $course->delete();
            Cache::tags('courses')->flush();

            return $this->successResponse(
                null,
                __('messages.courses.deleted')
            );

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(__('messages.courses.not_found'), 404);
        } catch (\Exception $e) {
            Log::error('Course deletion error', [
                'course_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->serverErrorResponse();
        }
    }
}
