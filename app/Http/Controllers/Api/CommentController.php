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
            'course_id' => 'required|exists:courses,id',
            'content' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
             throw new ValidationException($validator);
        }

        /** @var User $user */
        $user = Auth::user();
        $lesson = Lesson::with('course')->findOrFail($request->lesson_id);

        // التحقق من اشتراك المستخدم في الكورس أو إذا كان مديراً
        if ($user->Role !== 'admin' && !$user->subscriptions()->where('course_id', $lesson->course_id)->exists()) {
            return $this->errorResponse([
                'ar' => 'يجب أن تكون مشتركاً في الكورس لإضافة تعليق',
                'en' => 'You must be subscribed to the course to add a comment'
            ], 403);
        }

        $comment = Comment::create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'course_id' => $lesson->course_id, // إضافة معرف الكورس
            'content' => $request->content,
            'is_approved' => $user->Role === 'admin'
        ]);

        $message = $user->Role === 'admin'
            ? ['ar' => 'تم إضافة التعليق بنجاح', 'en' => 'Comment added successfully']
            : ['ar' => 'تم إضافة التعليق وسيظهر بعد الموافقة عليه', 'en' => 'Comment added and will appear after approval'];

        return $this->successResponse([
            'comment' => $comment->load(['user', 'lesson', 'course'])
        ], $message);

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
        $lesson = Lesson::with('course')->findOrFail($lessonId);
        /** @var User $user */
        $user = Auth::user();

        // التحقق من اشتراك المستخدم في الكورس أو إذا كان مديراً
        if ($user->Role !== 'admin' && !$user->subscriptions()->where('course_id', $lesson->course_id)->exists()) {
            return $this->errorResponse([
                'ar' => 'يجب أن تكون مشتركاً في الكورس لعرض التعليقات',
                'en' => 'You must be subscribed to the course to view comments'
            ], 403);
        }

        $comments = Comment::where('lesson_id', $lessonId)
            ->where('is_approved', true)
            ->with(['user', 'lesson', 'course'])
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
        $comment = Comment::with(['lesson', 'course'])->findOrFail($id);
        /** @var User $user */
        $user = Auth::user();

        // السماح بالحذف إذا كان المستخدم صاحب التعليق أو مديراً
        if ($comment->user_id !== $user->id && $user->Role !== 'admin') {
            return $this->errorResponse([
                'ar' => 'غير مصرح لك بحذف هذا التعليق',
                'en' => 'You are not authorized to delete this comment'
            ], 403);
        }

        // إذا كان التعليق معتمداً، لا يسمح إلا للمدير بحذفه
        if ($comment->is_approved && $user->Role !== 'admin') {
            return $this->errorResponse([
                'ar' => 'لا يمكن حذف تعليق معتمد إلا بواسطة المدير',
                'en' => 'Only admin can delete approved comments'
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
