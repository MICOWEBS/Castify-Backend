<?php

namespace App\Services;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoMetric;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    /**
     * Get personalized video recommendations for a user
     */
    public function getRecommendationsForUser(User $user, int $limit = 10): Collection
    {
        $cacheKey = 'recommendations_' . $user->id . '_' . $limit;
        
        return Cache::remember($cacheKey, 60 * 30, function () use ($user, $limit) {
            // Combine collaborative filtering and content-based recommendations
            $collaborativeRecs = $this->getCollaborativeFilteringRecommendations($user, $limit);
            $contentBasedRecs = $this->getContentBasedRecommendations($user, $limit);
            
            // Merge and remove duplicates
            $recommendations = $collaborativeRecs->merge($contentBasedRecs)->unique('id');
            
            // If we don't have enough, add trending videos
            if ($recommendations->count() < $limit) {
                $trending = $this->getTrendingVideos($limit - $recommendations->count());
                $recommendations = $recommendations->merge($trending)->unique('id');
            }
            
            return $recommendations->take($limit);
        });
    }
    
    /**
     * Get videos based on collaborative filtering (users with similar tastes)
     */
    protected function getCollaborativeFilteringRecommendations(User $user, int $limit): Collection
    {
        // Get videos that the user has watched and rated highly
        $userWatchedVideoIds = VideoMetric::where('user_id', $user->id)
            ->where('watch_time', '>', 0)
            ->pluck('video_id');
            
        if ($userWatchedVideoIds->isEmpty()) {
            return new Collection();
        }
        
        // Find users with similar viewing patterns
        $similarUserIds = VideoMetric::whereIn('video_id', $userWatchedVideoIds)
            ->where('user_id', '!=', $user->id)
            ->select('user_id', DB::raw('COUNT(*) as match_count'))
            ->groupBy('user_id')
            ->orderBy('match_count', 'desc')
            ->limit(20)
            ->pluck('user_id');
            
        if ($similarUserIds->isEmpty()) {
            return new Collection();
        }
        
        // Get videos watched by similar users but not by current user
        return Video::whereHas('metrics', function ($query) use ($similarUserIds) {
                $query->whereIn('user_id', $similarUserIds);
            })
            ->whereDoesntHave('metrics', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'published')
            ->withCount(['metrics as popularity' => function ($query) {
                $query->select(DB::raw('COUNT(DISTINCT user_id)'));
            }])
            ->orderBy('popularity', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get content-based recommendations (similar to what user has watched)
     */
    protected function getContentBasedRecommendations(User $user, int $limit): Collection
    {
        // Get categories of videos the user has watched
        $userCategoryIds = VideoMetric::where('user_id', $user->id)
            ->join('videos', 'video_metrics.video_id', '=', 'videos.id')
            ->join('category_video', 'videos.id', '=', 'category_video.video_id')
            ->pluck('category_video.category_id')
            ->unique();
            
        if ($userCategoryIds->isEmpty()) {
            return new Collection();
        }
        
        // Get videos in those categories that the user hasn't watched
        $watchedVideoIds = VideoMetric::where('user_id', $user->id)
            ->pluck('video_id');
            
        return Video::whereHas('categories', function ($query) use ($userCategoryIds) {
                $query->whereIn('categories.id', $userCategoryIds);
            })
            ->when(!$watchedVideoIds->isEmpty(), function ($query) use ($watchedVideoIds) {
                $query->whereNotIn('id', $watchedVideoIds);
            })
            ->where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get trending videos based on recent views
     */
    public function getTrendingVideos(int $limit = 10): Collection
    {
        $cacheKey = 'trending_videos_' . $limit;
        
        return Cache::remember($cacheKey, 60 * 60, function () use ($limit) {
            // Get videos with the most views in the last 7 days
            return Video::withCount(['metrics as recent_views' => function ($query) {
                    $query->where('created_at', '>=', now()->subDays(7));
                }])
                ->where('status', 'published')
                ->orderBy('recent_views', 'desc')
                ->limit($limit)
                ->get();
        });
    }
    
    /**
     * Get recently watched videos for a user
     */
    public function getRecentlyWatchedForUser(User $user, int $limit = 5): Collection
    {
        return Video::whereHas('metrics', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'published')
            ->orderBy('video_metrics.updated_at', 'desc')
            ->limit($limit)
            ->get();
    }
} 