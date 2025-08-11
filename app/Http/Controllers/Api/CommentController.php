
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Lesson;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
                return $this->validationErrorResponse($validator->errors());
            }

            $user = Auth::user();
            $lesson = Lesson::findOrFail($request->lesson_id);
            
            // Check if user is subscribed to the course or is admin
            if (!$user->isAdmin() && !$user->isSubscribedTo($lesson->course_id)) {
                return $this->errorResponse([
                    'ar' => 'يجب أن تكون مشتركاً في الكورس لإضافة تعليق',
                    'en' => 'You must be subscribed to the course to add a comment'
                ], 403);
            }

            $comment = Comment::create([
                'user_id' => $user->id,
                'lesson_id' => $request->lesson_id,
                'content' => $request->content,
                'is_approved' => $user->isAdmin() ? true : false
            ]);

            return $this->successResponse([
                'comment' => $comment->load(['user', 'lesson'])
            ], [
                'ar' => 'تم إضافة التعليق بنجاح',
                'en' => 'Comment added successfully'
            ]);

        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    public function getLessonComments($lessonId)
    {
        try {
            $lesson = Lesson::findOrFail($lessonId);
            $user = Auth::user();
            
            // Check if user is subscribed to the course or is admin
            if (!$user->isAdmin() && !$user->isSubscribedTo($lesson->course_id)) {
                return $this->errorResponse([
                    'ar' => 'يجب أن تكون مشتركاً في الكورس لعرض التعليقات',
                    'en' => 'You must be subscribed to the course to view comments'
                ], 403);
            }

            $comments = Comment::where('lesson_id', $lessonId)
                ->where('is_approved', true)
                ->with(['user'])
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse([
                'comments' => $comments
            ], [
                'ar' => 'تم جلب التعليقات بنجاح',
                'en' => 'Comments retrieved successfully'
            ]);

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

        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    public function destroy($id)
    {
        try {
            $comment = Comment::findOrFail($id);
            $user = Auth::user();
            
            // Allow deletion if user owns the comment or is admin
            if ($comment->user_id !== $user->id && !$user->isAdmin()) {
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

        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }
}
