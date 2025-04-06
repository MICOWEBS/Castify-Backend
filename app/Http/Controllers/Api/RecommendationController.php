<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(
 *     name="Recommendations",
 *     description="API Endpoints for video recommendations"
 * )
 */
class RecommendationController extends Controller
{
    protected RecommendationService $recommendationService;
    
    /**
     * Create a new controller instance.
     */
    public function __construct(RecommendationService $recommendationService)
    {
        $this->middleware('auth:sanctum');
        $this->recommendationService = $recommendationService;
    }
    
    /**
     * Get personalized recommendations for the authenticated user.
     * 
     * @OA\Get(
     *     path="/api/recommendations",
     *     summary="Get personalized video recommendations",
     *     tags={"Recommendations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of videos to return",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of recommended videos",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Video")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->input('limit', 10);
        
        $videos = $this->recommendationService->getRecommendationsForUser($user, $limit);
        
        return response()->json([
            'data' => $videos,
        ]);
    }
    
    /**
     * Get trending videos.
     * 
     * @OA\Get(
     *     path="/api/recommendations/trending",
     *     summary="Get trending videos",
     *     tags={"Recommendations"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of videos to return",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of trending videos",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Video")
     *             )
     *         )
     *     )
     * )
     */
    public function trending(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        
        $cacheKey = 'trending_videos_api_' . $limit;
        
        $videos = Cache::remember($cacheKey, 60 * 15, function () use ($limit) {
            return $this->recommendationService->getTrendingVideos($limit);
        });
        
        return response()->json([
            'data' => $videos,
        ]);
    }
    
    /**
     * Get recently watched videos for the authenticated user.
     * 
     * @OA\Get(
     *     path="/api/recommendations/recently-watched",
     *     summary="Get user's recently watched videos",
     *     tags={"Recommendations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of videos to return",
     *         required=false,
     *         @OA\Schema(type="integer", default=5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of recently watched videos",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Video")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function recentlyWatched(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->input('limit', 5);
        
        $videos = $this->recommendationService->getRecentlyWatchedForUser($user, $limit);
        
        return response()->json([
            'data' => $videos,
        ]);
    }
} 