<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\VideoMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get analytics for a specific video.
     */
    public function videoAnalytics(string $id): JsonResponse
    {
        $video = Video::with('metrics')->findOrFail($id);

        // Check if user owns the video or is an admin
        if (Auth::id() !== $video->user_id && !Auth::user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'data' => [
                'views' => $video->metrics->views ?? 0,
                'likes' => $video->metrics->likes ?? 0,
                'dislikes' => $video->metrics->dislikes ?? 0,
                'comments_count' => $video->metrics->comments_count ?? 0,
                'engagement_rate' => $this->calculateEngagementRate($video),
            ],
            'message' => 'Video analytics retrieved successfully',
        ]);
    }

    /**
     * Get analytics for all videos of the authenticated user.
     */
    public function userVideosAnalytics(): JsonResponse
    {
        $videos = Video::where('user_id', Auth::id())
            ->with('metrics')
            ->get();

        $totalViews = 0;
        $totalLikes = 0;
        $totalComments = 0;
        $videoCount = count($videos);

        foreach ($videos as $video) {
            $totalViews += $video->metrics->views ?? 0;
            $totalLikes += $video->metrics->likes ?? 0;
            $totalComments += $video->metrics->comments_count ?? 0;
        }

        // Get top performing videos
        $topVideos = Video::where('user_id', Auth::id())
            ->orderBy('view_count', 'desc')
            ->with('metrics')
            ->take(5)
            ->get()
            ->map(function ($video) {
                return [
                    'id' => $video->id,
                    'title' => $video->title,
                    'views' => $video->metrics->views ?? 0,
                    'likes' => $video->metrics->likes ?? 0,
                ];
            });

        return response()->json([
            'data' => [
                'total_videos' => $videoCount,
                'total_views' => $totalViews,
                'total_likes' => $totalLikes,
                'total_comments' => $totalComments,
                'average_views_per_video' => $videoCount > 0 ? round($totalViews / $videoCount, 2) : 0,
                'top_performing_videos' => $topVideos,
            ],
            'message' => 'User videos analytics retrieved successfully',
        ]);
    }

    /**
     * Get analytics for all videos in the system (admin only).
     */
    public function adminAnalytics(): JsonResponse
    {
        // Check if user is an admin
        if (!Auth::user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // Get total videos, views, likes, etc.
        $totalVideos = Video::count();
        $totalViews = VideoMetric::sum('views');
        $totalLikes = VideoMetric::sum('likes');
        $totalComments = VideoMetric::sum('comments_count');

        // Get videos by category
        $videosByCategory = DB::table('categories')
            ->join('category_video', 'categories.id', '=', 'category_video.category_id')
            ->select('categories.name', DB::raw('count(*) as count'))
            ->groupBy('categories.name')
            ->get();

        // Get top 5 videos
        $topVideos = Video::orderBy('view_count', 'desc')
            ->with(['user:id,first_name,last_name', 'metrics'])
            ->take(5)
            ->get()
            ->map(function ($video) {
                return [
                    'id' => $video->id,
                    'title' => $video->title,
                    'user' => $video->user->first_name . ' ' . $video->user->last_name,
                    'views' => $video->metrics->views ?? 0,
                    'likes' => $video->metrics->likes ?? 0,
                ];
            });

        // Get top 5 users
        $topUsers = DB::table('users')
            ->join('videos', 'users.id', '=', 'videos.user_id')
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                DB::raw('count(videos.id) as video_count'),
                DB::raw('sum(videos.view_count) as total_views')
            )
            ->groupBy('users.id', 'users.first_name', 'users.last_name')
            ->orderBy('total_views', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'data' => [
                'total_videos' => $totalVideos,
                'total_views' => $totalViews,
                'total_likes' => $totalLikes,
                'total_comments' => $totalComments,
                'videos_by_category' => $videosByCategory,
                'top_videos' => $topVideos,
                'top_users' => $topUsers,
            ],
            'message' => 'Admin analytics retrieved successfully',
        ]);
    }

    /**
     * Calculate engagement rate for a video.
     *
     * Engagement rate = (likes + comments) / views * 100
     */
    private function calculateEngagementRate(Video $video): float
    {
        if (!$video->metrics || $video->metrics->views === 0) {
            return 0;
        }

        $engagement = ($video->metrics->likes + $video->metrics->comments_count) / $video->metrics->views * 100;
        return round($engagement, 2);
    }
}
