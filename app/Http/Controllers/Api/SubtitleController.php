<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Subtitles",
 *     description="API Endpoints for video subtitle management"
 * )
 */
class SubtitleController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin')->only(['generateSubtitles']);
    }

    /**
     * List all subtitles for a video
     * 
     * @OA\Get(
     *     path="/api/videos/{video}/subtitles",
     *     summary="Get all subtitles for a video",
     *     tags={"Subtitles"},
     *     @OA\Parameter(
     *         name="video",
     *         in="path",
     *         description="Video ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of subtitles",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="language", type="string", example="en"),
     *                 @OA\Property(property="name", type="string", example="English"),
     *                 @OA\Property(property="url", type="string", example="https://example.com/subtitles/1/en.vtt")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Video not found")
     * )
     */
    public function index(Video $video): JsonResponse
    {
        if (!$video->has_subtitles || empty($video->subtitle_languages)) {
            return response()->json(['data' => []], 200);
        }

        $languages = json_decode($video->subtitle_languages, true) ?? [];
        $subtitles = [];

        foreach ($languages as $langCode) {
            $subtitles[] = [
                'language' => $langCode,
                'name' => $this->getLanguageName($langCode),
                'url' => url("/storage/subtitles/{$video->id}/{$langCode}.vtt"),
            ];
        }

        return response()->json(['data' => $subtitles]);
    }

    /**
     * Upload a subtitle file for a video
     * 
     * @OA\Post(
     *     path="/api/videos/{video}/subtitles",
     *     summary="Upload a subtitle file",
     *     tags={"Subtitles"},
     *     @OA\Parameter(
     *         name="video",
     *         in="path",
     *         description="Video ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="subtitle", type="file", description="Subtitle file (VTT/SRT)"),
     *                 @OA\Property(property="language", type="string", description="Language code (e.g., en, es, fr)"),
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Subtitle added successfully"),
     *     @OA\Response(response=400, description="Invalid request"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Video not found")
     * )
     */
    public function store(Request $request, Video $video): JsonResponse
    {
        // Check if user owns the video or is admin
        if ($video->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'subtitle' => 'required|file|mimes:txt,vtt,srt|max:2048',
            'language' => 'required|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        // Store the subtitle file
        $file = $request->file('subtitle');
        $language = $request->input('language');
        $path = "subtitles/{$video->id}";
        $filename = "{$language}.vtt";

        // Convert SRT to VTT if needed (in a production app)
        // For this demo, we'll just rename the file

        // Store the file
        $file->storeAs($path, $filename, 'public');
        
        // Update the video with subtitle info
        $languages = json_decode($video->subtitle_languages, true) ?? [];
        if (!in_array($language, $languages)) {
            $languages[] = $language;
            $video->subtitle_languages = json_encode($languages);
        }
        
        $video->has_subtitles = true;
        $video->save();

        return response()->json([
            'message' => 'Subtitle added successfully',
            'data' => [
                'language' => $language,
                'name' => $this->getLanguageName($language),
                'url' => url("/storage/{$path}/{$filename}"),
            ]
        ], 201);
    }

    /**
     * Delete a subtitle
     * 
     * @OA\Delete(
     *     path="/api/videos/{video}/subtitles/{language}",
     *     summary="Delete a subtitle",
     *     tags={"Subtitles"},
     *     @OA\Parameter(
     *         name="video",
     *         in="path",
     *         description="Video ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="path",
     *         description="Language code (e.g., en, es, fr)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Subtitle deleted successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Subtitle not found")
     * )
     */
    public function destroy(Video $video, string $language): JsonResponse
    {
        // Check if user owns the video or is admin
        if ($video->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $languages = json_decode($video->subtitle_languages, true) ?? [];
        
        if (!in_array($language, $languages)) {
            return response()->json(['message' => 'Subtitle not found'], 404);
        }

        // Remove the file
        $path = "subtitles/{$video->id}/{$language}.vtt";
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        // Update the video
        $languages = array_diff($languages, [$language]);
        $video->subtitle_languages = !empty($languages) ? json_encode(array_values($languages)) : null;
        $video->has_subtitles = !empty($languages);
        $video->save();

        return response()->json(['message' => 'Subtitle deleted successfully']);
    }

    /**
     * Generate subtitles for a video using AI
     * 
     * @OA\Post(
     *     path="/api/videos/{video}/subtitles/generate",
     *     summary="Generate subtitles using AI (admin only)",
     *     tags={"Subtitles"},
     *     @OA\Parameter(
     *         name="video",
     *         in="path",
     *         description="Video ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="languages", type="array", @OA\Items(type="string")),
     *             )
     *         )
     *     ),
     *     @OA\Response(response=202, description="Subtitle generation started"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Video not found")
     * )
     */
    public function generateSubtitles(Request $request, Video $video): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'languages' => 'required|array',
            'languages.*' => 'required|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        // In a real app, we would dispatch a job to generate subtitles
        // For this demo, we'll just update the video

        $languages = $request->input('languages');
        $existingLanguages = json_decode($video->subtitle_languages, true) ?? [];
        $allLanguages = array_unique(array_merge($existingLanguages, $languages));

        $video->subtitle_languages = json_encode($allLanguages);
        $video->has_subtitles = true;
        $video->save();

        return response()->json([
            'message' => 'Subtitle generation started',
            'data' => [
                'languages' => $languages,
            ]
        ], 202);
    }

    /**
     * Helper to get language name from code
     */
    private function getLanguageName(string $code): string
    {
        $languages = [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'ar' => 'Arabic',
            'hi' => 'Hindi',
        ];

        return $languages[$code] ?? ucfirst($code);
    }
} 