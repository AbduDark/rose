<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index($lessonId)
    {
        $comments = Comment::with('user:id,name,profile_image')
            ->where('lesson_id', $lessonId)
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($comments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
            'content' => 'required|string|max:1000',
        ]);

        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'lesson_id' => $request->lesson_id,
            'content' => $request->content,
            'is_approved' => false, // Requires admin approval
        ]);

        return response()->json([
            'message' => 'Comment submitted successfully. It will be visible after approval.',
            'comment' => $comment
        ], 201);
    }

    public function approve($id)
    {
        $comment = Comment::findOrFail($id);
        $comment->update(['is_approved' => true]);

        return response()->json([
            'message' => 'Comment approved successfully',
            'comment' => $comment
        ]);
    }

    public function reject($id)
    {
        $comment = Comment::findOrFail($id);
        $comment->delete();

        return response()->json(['message' => 'Comment rejected and deleted']);
    }

    public function pending()
    {
        $comments = Comment::with(['user:id,name,profile_image', 'lesson.course'])
            ->where('is_approved', false)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($comments);
    }
}
