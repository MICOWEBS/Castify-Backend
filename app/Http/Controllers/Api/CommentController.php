<?php

namespace App\Http\Controllers\Api;

use App\Events\CommentCreated;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Video;
use App\Models\VideoMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Event;

class CommentController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|exists:videos,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $comments = Comment::where('video_id', $request->video_id)
            ->where('is_approved', true)
            ->with('user:id,first_name,last_name')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $comments,
            'message' => 'Comments retrieved successfully',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|exists:videos,id',
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $comment = Comment::create([
            'user_id' => Auth::id(),
            'video_id' => $request->video_id,
            'content' => $request->content,
            'is_approved' => true, // Auto-approve comments (for simplicity)
        ]);

        // Load user relation for the response
        $comment->load('user:id,first_name,last_name');

        // Increment comment count in metrics
        $videoMetric = VideoMetric::where('video_id', $request->video_id)->first();
        if ($videoMetric) {
            $videoMetric->increment('comments_count');
        }

        // Dispatch event for real-time updates
        event(new CommentCreated($comment));

        return response()->json([
            'message' => 'Comment created successfully',
            'data' => $comment,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $comment = Comment::with('user:id,first_name,last_name')->findOrFail($id);

        // Check if user owns the comment or is an admin or the video owner
        $video = Video::find($comment->video_id);
        if (!$comment->is_approved && Auth::id() !== $comment->user_id && 
            Auth::id() !== $video->user_id && !Auth::user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'data' => $comment,
            'message' => 'Comment retrieved successfully',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);

        // Check if user owns the comment
        if (Auth::id() !== $comment->user_id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $comment->update([
            'content' => $request->content,
            // Reset approval in case moderation is needed after edit
            'is_approved' => Auth::user()->isAdmin() ? true : $comment->is_approved,
        ]);

        // In a real app, broadcast event for real-time updates
        // Event::dispatch(new CommentUpdated($comment));

        return response()->json([
            'message' => 'Comment updated successfully',
            'data' => $comment,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);

        // Check if user owns the comment or is an admin or the video owner
        $video = Video::find($comment->video_id);
        if (Auth::id() !== $comment->user_id && 
            Auth::id() !== $video->user_id && !Auth::user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // Decrement comment count in metrics
        $videoMetric = VideoMetric::where('video_id', $comment->video_id)->first();
        if ($videoMetric) {
            $videoMetric->decrement('comments_count');
        }

        $comment->delete();

        // In a real app, broadcast event for real-time updates
        // Event::dispatch(new CommentDeleted($comment->id, $comment->video_id));

        return response()->json([
            'message' => 'Comment deleted successfully',
        ]);
    }

    /**
     * Approve a comment (admin only).
     */
    public function approve(string $id): JsonResponse
    {
        // Only admins can approve comments
        if (!Auth::user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $comment = Comment::findOrFail($id);
        $comment->update(['is_approved' => true]);

        return response()->json([
            'message' => 'Comment approved successfully',
            'data' => $comment,
        ]);
    }

    /**
     * Like a video.
     */
    public function likeVideo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|exists:videos,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $videoMetric = VideoMetric::where('video_id', $request->video_id)->first();
        if ($videoMetric) {
            $videoMetric->increment('likes');
        }

        return response()->json([
            'message' => 'Video liked successfully',
        ]);
    }

    /**
     * Dislike a video.
     */
    public function dislikeVideo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|exists:videos,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $videoMetric = VideoMetric::where('video_id', $request->video_id)->first();
        if ($videoMetric) {
            $videoMetric->increment('dislikes');
        }

        return response()->json([
            'message' => 'Video disliked successfully',
        ]);
    }
}
