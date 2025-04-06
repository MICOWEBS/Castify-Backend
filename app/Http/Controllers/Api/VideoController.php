<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVideoRequest;
use App\Jobs\ProcessVideoJob;
use App\Models\Video;
use App\Models\VideoMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(
 *     name="Videos",
 *     description="API Endpoints for video management"
 * )
 */
class VideoController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index', 'show', 'search']);
        $this->middleware('verified.email')->except(['index', 'show']);
    }

    /**
     * Display a listing of the resource.
     * 
     * @OA\Get(
     *     path="/api/videos",
     *     summary="Get list of videos",
     *     tags={"Videos"},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter videos by category ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for videos",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of videos",
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
    public function index(Request $request): JsonResponse
    {
        $page = $request->get('page', 1);
        $category = $request->get('category_id');
        $trending = $request->get('trending', false);
        
        // Create a cache key based on request parameters
        $cacheKey = 'videos_' . ($category ? 'category_' . $category . '_' : '') . 
                   ($trending ? 'trending_' : '') . 'page_' . $page;
        
        // Try to get from cache first
        $videos = cache()->remember($cacheKey, now()->addMinutes(10), function () use ($request) {
            $query = Video::with(['user:id,first_name,last_name', 'categories'])
                ->where('status', 'complete');
    
            // Filter by category if provided
            if ($request->has('category_id')) {
                $query->inCategory($request->category_id);
            }
    
            // Sort by trending (view count) if requested
            if ($request->has('trending') && $request->trending) {
                $query->orderBy('view_count', 'desc');
            } else {
                $query->latest();
            }
    
            return $query->paginate(10);
        });

        return response()->json([
            'data' => $videos,
            'message' => 'Videos retrieved successfully',
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
     * 
     * @OA\Post(
     *     path="/api/videos",
     *     summary="Upload a new video",
     *     tags={"Videos"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/StoreVideoRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Video created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/Video"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Video created successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="The given data was invalid."
     *             ),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Unauthenticated."
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreVideoRequest $request): JsonResponse
    {
        // Check if user is verified
        if (!Auth::user()->is_verified) {
            return response()->json([
                'message' => 'You need to verify your email address before uploading videos',
            ], 403);
        }

        DB::beginTransaction();

        try {
            // Store video file
            $videoPath = $request->file('video')->store('videos', 'public');
            
            // Store thumbnail if provided, or generate one (in a real app)
            $thumbnailPath = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
            }

            // Create video record
            $video = Video::create([
                'title' => $request->title,
                'description' => $request->description,
                'user_id' => Auth::id(),
                'file_path' => $videoPath,
                'thumbnail_path' => $thumbnailPath,
                'status' => 'processing', // Will be set to complete after processing
            ]);

            // Attach categories
            $video->categories()->attach($request->categories);

            // Create video metrics
            VideoMetric::create([
                'video_id' => $video->id,
            ]);

            // Dispatch job for video processing
            ProcessVideoJob::dispatch($video);

            DB::commit();

            return response()->json([
                'message' => 'Video uploaded successfully and is being processed',
                'data' => $video,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to upload video',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     * 
     * @OA\Get(
     *     path="/api/videos/{id}",
     *     summary="Get video details",
     *     tags={"Videos"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Video ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Video details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/Video"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Video not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="No video found with the specified identifier."
     *             )
     *         )
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        // Cache video details for 10 minutes
        $video = cache()->remember('video_' . $id, now()->addMinutes(10), function () use ($id) {
            return Video::with([
                'user:id,first_name,last_name',
                'categories',
                'metrics',
                'comments' => function ($query) {
                    $query->where('is_approved', true)
                          ->with('user:id,first_name,last_name')
                          ->latest();
                }
            ])->findOrFail($id);
        });

        // Increment view count
        $video->incrementViewCount();
        
        // Update the cache with new view count
        cache()->put('video_' . $id, $video->fresh(['metrics']), now()->addMinutes(10));

        return response()->json([
            'data' => $video,
            'message' => 'Video retrieved successfully',
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
     * 
     * @OA\Patch(
     *     path="/api/videos/{id}",
     *     summary="Update a video",
     *     tags={"Videos"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Video ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="thumbnail", type="string"),
     *             @OA\Property(property="category_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Video updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/Video"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Video updated successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden action",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="You do not have permission to update this video."
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Video not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="No video found with the specified identifier."
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $video = Video::findOrFail($id);

        // Check if user owns the video or is an admin
        if (Auth::id() !== $video->user_id && !Auth::user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|max:2048',
            'categories' => 'sometimes|required|array',
            'categories.*' => 'exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Update thumbnail if provided
            if ($request->hasFile('thumbnail')) {
                // Delete old thumbnail if exists
                if ($video->thumbnail_path) {
                    Storage::disk('public')->delete($video->thumbnail_path);
                }
                
                $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
                $video->thumbnail_path = $thumbnailPath;
            }

            // Update video details
            if ($request->has('title')) {
                $video->title = $request->title;
            }
            
            if ($request->has('description')) {
                $video->description = $request->description;
            }

            $video->save();

            // Update categories if provided
            if ($request->has('categories')) {
                $video->categories()->sync($request->categories);
            }

            DB::commit();

            return response()->json([
                'message' => 'Video updated successfully',
                'data' => $video->fresh(['categories']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update video',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     * 
     * @OA\Delete(
     *     path="/api/videos/{id}",
     *     summary="Delete a video",
     *     tags={"Videos"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Video ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Video deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Video deleted successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden action",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="You do not have permission to delete this video."
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Video not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="No video found with the specified identifier."
     *             )
     *         )
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $video = Video::findOrFail($id);

        // Check if user owns the video or is an admin
        if (Auth::id() !== $video->user_id && !Auth::user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        DB::beginTransaction();

        try {
            // Delete video file
            if ($video->file_path) {
                Storage::disk('public')->delete($video->file_path);
            }

            // Delete thumbnail if exists
            if ($video->thumbnail_path) {
                Storage::disk('public')->delete($video->thumbnail_path);
            }

            // Delete related records (metrics, comments, etc.)
            // Note: With cascading deletes in migrations, this happens automatically

            // Delete the video
            $video->delete();

            DB::commit();

            return response()->json([
                'message' => 'Video deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete video',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search for videos.
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $videos = Video::search($request->query)
            ->where('status', 'complete')
            ->paginate(10);

        return response()->json([
            'data' => $videos,
            'message' => 'Search results retrieved successfully',
        ]);
    }

    /**
     * Get video analytics.
     */
    public function analytics(string $id): JsonResponse
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
                'views' => $video->metrics->views,
                'likes' => $video->metrics->likes,
                'dislikes' => $video->metrics->dislikes,
                'comments_count' => $video->metrics->comments_count,
            ],
            'message' => 'Video analytics retrieved successfully',
        ]);
    }
}
