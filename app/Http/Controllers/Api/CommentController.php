<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Lesson;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;


class CommentController extends Controller
{
    use ApiResponseTrait;

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'lesson_id' => 'required|exists:lessons,id',
                'content' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                 throw new ValidationException($validator);
            }

            /** @var User $user */
            $user = Auth::user();
            $lesson = Lesson::findOrFail($request->lesson_id);

            // Check if user is subscribed to the course or is admin
            if (!$user->Role === 'admin' && !$user->subscriptions()->where('course_id', $lesson->course_id)->exists()) {
                return $this->errorResponse([
                    'ar' => 'يجب أن تكون مشتركاً في الكورس لإضافة تعليق',
                    'en' => 'You must be subscribed to the course to add a comment'
                ], 403);
            }

            $comment = Comment::create([
                'user_id' => $user->id,
                'lesson_id' => $request->lesson_id,
                'content' => $request->content,
                'is_approved' => $user->Role === 'admin'
            ]);

            return $this->successResponse([
                'comment' => $comment->load(['user', 'lesson'])
            ], [
                'ar' => 'تم إضافة التعليق بنجاح',
                'en' => 'Comment added successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'الدرس المطلوب غير موجود',
                'en' => 'The requested lesson does not exist'
            ], 404);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    public function getLessonComments($lessonId)
    {
        try {
            $lesson = Lesson::findOrFail($lessonId);
            /** @var User $user */
            $user = Auth::user();

            // Check if user is subscribed to the course or is admin
            if (!$user->Role === 'admin' && !$user->subscriptions()->where('course_id', $lesson->course_id)->exists()) {
                return $this->errorResponse([
                    'ar' => 'يجب أن تكون مشتركاً في الكورس لعرض التعليقات',
                    'en' => 'You must be subscribed to the course to view comments'
                ], 403);
            }

            $comments = Comment::where('lesson_id', $lessonId)
                ->where('is_approved', true)
                ->with(['user'])
                ->latest()
                ->get();

            return $this->successResponse([
                'comments' => $comments
            ], [
                'ar' => 'تم جلب التعليقات بنجاح',
                'en' => 'Comments retrieved successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'الدرس المطلوب غير موجود',
                'en' => 'The requested lesson does not exist'
            ], 404);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    public function approveComment($id)
    {
        try {
            $comment = Comment::findOrFail($id);

            $comment->update(['is_approved' => true]);

            return $this->successResponse([
                'comment' => $comment->load(['user', 'lesson'])
            ], [
                'ar' => 'تم الموافقة على التعليق',
                'en' => 'Comment approved successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'التعليق المطلوب غير موجود',
                'en' => 'The requested comment does not exist'
            ], 404);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    public function destroy($id)
    {
        try {
            $comment = Comment::findOrFail($id);
            /** @var User $user */
            $user = Auth::user();

            // Allow deletion if user owns the comment or is admin
            if ($comment->user_id !== $user->id && !$user->Role === 'admin') {
                return $this->errorResponse([
                    'ar' => 'غير مصرح لك بحذف هذا التعليق',
                    'en' => 'You are not authorized to delete this comment'
                ], 403);
            }

            $comment->delete();

            return $this->successResponse(null, [
                'ar' => 'تم حذف التعليق بنجاح',
                'en' => 'Comment deleted successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'التعليق المطلوب غير موجود',
                'en' => 'The requested comment does not exist'
            ], 404);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }
}
