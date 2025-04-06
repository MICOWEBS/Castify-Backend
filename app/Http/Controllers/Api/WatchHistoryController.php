<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use App\Models\Video;
use App\Models\WatchHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Watch History",
 *     description="API Endpoints for watch history and playback progress"
 * )
 */
class WatchHistoryController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    
    /**
     * Get watch history for the authenticated user or a specific profile.
     * 
     * @OA\Get(
     *     path="/api/watch-history",
     *     summary="Get watch history",
     *     tags={"Watch History"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="profile_id",
     *         in="query",
     *         description="Filter by profile ID (optional)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of records to return",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of watch history entries",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="video", ref="#/components/schemas/Video"),
     *                     @OA\Property(property="watched_seconds", type="integer"),
     *                     @OA\Property(property="video_duration", type="integer"),
     *                     @OA\Property(property="progress_percentage", type="integer"),
     *                     @OA\Property(property="completed", type="boolean"),
     *                     @OA\Property(property="last_watched_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $profileId = $request->input('profile_id');
        $limit = $request->input('limit', 20);
        
        $query = WatchHistory::with('video')
            ->where('user_id', $userId)
            ->orderBy('last_watched_at', 'desc')
            ->limit($limit);
        
        // Filter by profile if specified
        if ($profileId) {
            // Verify profile belongs to user
            $profile = UserProfile::where('id', $profileId)
                ->where('user_id', $userId)
                ->firstOrFail();
                
            $query->where('user_profile_id', $profile->id);
        }
        
        $history = $query->get();
        
        // Format the response
        $data = $history->map(function ($entry) {
            return [
                'id' => $entry->id,
                'video' => $entry->video,
                'watched_seconds' => $entry->watched_seconds,
                'video_duration' => $entry->video_duration,
                'progress_percentage' => $entry->progress_percentage,
                'completed' => $entry->completed,
                'last_watched_at' => $entry->last_watched_at,
                'can_resume' => $entry->can_resume,
            ];
        });
        
        return response()->json([
            'data' => $data,
        ]);
    }
    
    /**
     * Get continue watching items (incomplete videos).
     * 
     * @OA\Get(
     *     path="/api/watch-history/continue-watching",
     *     summary="Get videos that can be resumed",
     *     tags={"Watch History"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="profile_id",
     *         in="query",
     *         description="Filter by profile ID (optional)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of records to return",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of videos that can be resumed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="video", ref="#/components/schemas/Video"),
     *                     @OA\Property(property="watched_seconds", type="integer"),
     *                     @OA\Property(property="progress_percentage", type="integer"),
     *                     @OA\Property(property="last_watched_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function continueWatching(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $profileId = $request->input('profile_id');
        $limit = $request->input('limit', 10);
        
        $query = WatchHistory::with('video')
            ->where('user_id', $userId)
            ->where('completed', false)
            // Videos with 5% to 95% progress
            ->whereRaw('(watched_seconds / video_duration) BETWEEN 0.05 AND 0.95')
            ->orderBy('last_watched_at', 'desc')
            ->limit($limit);
        
        // Filter by profile if specified
        if ($profileId) {
            // Verify profile belongs to user
            $profile = UserProfile::where('id', $profileId)
                ->where('user_id', $userId)
                ->firstOrFail();
                
            $query->where('user_profile_id', $profile->id);
        }
        
        $history = $query->get();
        
        // Format the response
        $data = $history->map(function ($entry) {
            return [
                'id' => $entry->id,
                'video' => $entry->video,
                'watched_seconds' => $entry->watched_seconds,
                'progress_percentage' => $entry->progress_percentage,
                'last_watched_at' => $entry->last_watched_at,
            ];
        });
        
        return response()->json([
            'data' => $data,
        ]);
    }
    
    /**
     * Get playback position for a specific video.
     * 
     * @OA\Get(
     *     path="/api/videos/{videoId}/playback-position",
     *     summary="Get playback position for a video",
     *     tags={"Watch History"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="videoId",
     *         in="path",
     *         description="Video ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="profile_id",
     *         in="query",
     *         description="Profile ID (optional)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Playback position details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="watched_seconds", type="integer", example=360),
     *             @OA\Property(property="progress_percentage", type="integer", example=30),
     *             @OA\Property(property="completed", type="boolean", example=false),
     *             @OA\Property(property="can_resume", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No watch history found"
     *     )
     * )
     */
    public function playbackPosition(Request $request, string $videoId): JsonResponse
    {
        $userId = $request->user()->id;
        $profileId = $request->input('profile_id');
        
        $query = WatchHistory::where('user_id', $userId)
            ->where('video_id', $videoId);
        
        // Filter by profile if specified
        if ($profileId) {
            // Verify profile belongs to user
            $profile = UserProfile::where('id', $profileId)
                ->where('user_id', $userId)
                ->firstOrFail();
                
            $query->where('user_profile_id', $profile->id);
        } else {
            $query->whereNull('user_profile_id');
        }
        
        $history = $query->first();
        
        if (!$history) {
            return response()->json([
                'watched_seconds' => 0,
                'progress_percentage' => 0,
                'completed' => false,
                'can_resume' => false,
            ]);
        }
        
        return response()->json([
            'watched_seconds' => $history->watched_seconds,
            'progress_percentage' => $history->progress_percentage,
            'completed' => $history->completed,
            'can_resume' => $history->can_resume,
        ]);
    }
    
    /**
     * Update playback progress.
     * 
     * @OA\Post(
     *     path="/api/watch-history/progress",
     *     summary="Update video playback progress",
     *     tags={"Watch History"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"video_id", "watched_seconds", "video_duration"},
     *             @OA\Property(property="video_id", type="integer", example=1),
     *             @OA\Property(property="watched_seconds", type="integer", example=360),
     *             @OA\Property(property="video_duration", type="integer", example=1200),
     *             @OA\Property(property="profile_id", type="integer", example=1),
     *             @OA\Property(property="completed", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Progress updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Progress updated successfully"
     *             ),
     *             @OA\Property(
     *                 property="progress_percentage",
     *                 type="integer",
     *                 example=30
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Video or profile not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function updateProgress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|exists:videos,id',
            'watched_seconds' => 'required|integer|min:0',
            'video_duration' => 'required|integer|min:1',
            'profile_id' => 'nullable|exists:user_profiles,id',
            'completed' => 'nullable|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        $userId = $request->user()->id;
        $videoId = $request->video_id;
        $profileId = $request->profile_id;
        $watchedSeconds = $request->watched_seconds;
        $videoDuration = $request->video_duration;
        
        // Verify video exists
        $video = Video::findOrFail($videoId);
        
        // Verify profile belongs to user if specified
        if ($profileId) {
            $profile = UserProfile::where('id', $profileId)
                ->where('user_id', $userId)
                ->firstOrFail();
        }
        
        // Calculate completion status
        $completed = $request->input('completed', false);
        if ($watchedSeconds >= $videoDuration * 0.95) {
            $completed = true;
        }
        
        // Find or create watch history entry
        $history = WatchHistory::updateOrCreate(
            [
                'user_id' => $userId,
                'video_id' => $videoId,
                'user_profile_id' => $profileId,
            ],
            [
                'watched_seconds' => $watchedSeconds,
                'video_duration' => $videoDuration,
                'completed' => $completed,
                'last_watched_at' => now(),
            ]
        );
        
        // Update video metrics (increment view count if first time or completed)
        $this->updateVideoMetrics($video, $userId, $completed, $history->wasRecentlyCreated);
        
        return response()->json([
            'message' => 'Progress updated successfully',
            'progress_percentage' => $history->progress_percentage,
        ]);
    }
    
    /**
     * Delete a watch history entry.
     * 
     * @OA\Delete(
     *     path="/api/watch-history/{id}",
     *     summary="Delete a watch history entry",
     *     tags={"Watch History"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Watch history entry ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Watch history entry deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Watch history entry deleted successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Watch history entry not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $history = WatchHistory::findOrFail($id);
        
        // Check if the history entry belongs to the authenticated user
        if ($history->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You do not have permission to delete this watch history entry',
            ], 403);
        }
        
        $history->delete();
        
        return response()->json([
            'message' => 'Watch history entry deleted successfully',
        ]);
    }
    
    /**
     * Clear all watch history for the user or a specific profile.
     * 
     * @OA\Delete(
     *     path="/api/watch-history",
     *     summary="Clear watch history",
     *     tags={"Watch History"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="profile_id",
     *         in="query",
     *         description="Clear history only for this profile",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Watch history cleared successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Watch history cleared successfully"
     *             ),
     *             @OA\Property(
     *                 property="entries_deleted",
     *                 type="integer",
     *                 example=15
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Profile not found"
     *     )
     * )
     */
    public function clearHistory(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $profileId = $request->input('profile_id');
        
        $query = WatchHistory::where('user_id', $userId);
        
        // Filter by profile if specified
        if ($profileId) {
            // Verify profile belongs to user
            $profile = UserProfile::where('id', $profileId)
                ->where('user_id', $userId)
                ->firstOrFail();
                
            $query->where('user_profile_id', $profile->id);
        }
        
        $count = $query->count();
        $query->delete();
        
        return response()->json([
            'message' => 'Watch history cleared successfully',
            'entries_deleted' => $count,
        ]);
    }
    
    /**
     * Update video metrics based on watch progress.
     */
    protected function updateVideoMetrics(Video $video, int $userId, bool $completed, bool $firstView): void
    {
        if ($firstView) {
            // Create a new metric entry if it's the first time watching
            DB::table('video_metrics')->insert([
                'video_id' => $video->id,
                'user_id' => $userId,
                'views' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else if ($completed) {
            // Increment view count if the video was completed
            DB::table('video_metrics')
                ->where('video_id', $video->id)
                ->where('user_id', $userId)
                ->increment('views');
        }
        
        // Update the last_updated timestamp
        DB::table('video_metrics')
            ->where('video_id', $video->id)
            ->where('user_id', $userId)
            ->update(['updated_at' => now()]);
    }
} 