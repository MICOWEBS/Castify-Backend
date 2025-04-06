<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="User Profiles",
 *     description="API Endpoints for user profile management"
 * )
 */
class UserProfileController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    
    /**
     * Get all profiles for the authenticated user.
     * 
     * @OA\Get(
     *     path="/api/profiles",
     *     summary="Get all user profiles",
     *     tags={"User Profiles"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of user profiles",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/UserProfile")
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
        $profiles = $request->user()->profiles()->orderBy('is_default', 'desc')->get();
        
        return response()->json([
            'data' => $profiles,
        ]);
    }
    
    /**
     * Store a newly created profile.
     * 
     * @OA\Post(
     *     path="/api/profiles",
     *     summary="Create a new user profile",
     *     tags={"User Profiles"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Kids Profile"),
     *             @OA\Property(property="is_kids_profile", type="boolean", example=true),
     *             @OA\Property(property="content_preferences", type="array", @OA\Items(type="string"), example={"animation", "family"}),
     *             @OA\Property(property="max_content_rating", type="string", example="PG"),
     *             @OA\Property(property="is_default", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Profile created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/UserProfile"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Profile created successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'is_kids_profile' => 'boolean',
            'content_preferences' => 'nullable|array',
            'content_preferences.*' => 'string',
            'max_content_rating' => 'nullable|string|in:G,PG,PG-13,R,NC-17',
            'is_default' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        $user = $request->user();
        
        // Check profile limit (e.g., max 5 profiles per account)
        $profileCount = $user->profiles()->count();
        if ($profileCount >= 5) {
            return response()->json([
                'message' => 'Maximum number of profiles reached (5)',
            ], 403);
        }
        
        // If this is the first profile or setting as default, 
        // make sure there's only one default profile
        if ($request->input('is_default', false) || $profileCount === 0) {
            $user->profiles()->update(['is_default' => false]);
        }
        
        // Create profile
        $profile = new UserProfile([
            'user_id' => $user->id,
            'name' => $request->name,
            'is_kids_profile' => $request->input('is_kids_profile', false),
            'max_content_rating' => $request->input('max_content_rating', $request->input('is_kids_profile', false) ? 'PG' : 'PG-13'),
            'is_default' => $request->input('is_default', $profileCount === 0),
        ]);
        
        // Handle content preferences
        if ($request->has('content_preferences')) {
            $profile->content_preferences = implode(',', $request->content_preferences);
        }
        
        $profile->save();
        
        return response()->json([
            'data' => $profile,
            'message' => 'Profile created successfully',
        ], 201);
    }
    
    /**
     * Display the specified profile.
     * 
     * @OA\Get(
     *     path="/api/profiles/{id}",
     *     summary="Get profile details",
     *     tags={"User Profiles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Profile ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/UserProfile"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Profile not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $profile = UserProfile::findOrFail($id);
        
        // Check if the profile belongs to the authenticated user
        if ($profile->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You do not have permission to view this profile',
            ], 403);
        }
        
        return response()->json([
            'data' => $profile,
        ]);
    }
    
    /**
     * Update the specified profile.
     * 
     * @OA\Patch(
     *     path="/api/profiles/{id}",
     *     summary="Update a profile",
     *     tags={"User Profiles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Profile ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Profile"),
     *             @OA\Property(property="is_kids_profile", type="boolean", example=false),
     *             @OA\Property(property="content_preferences", type="array", @OA\Items(type="string"), example={"action", "comedy"}),
     *             @OA\Property(property="max_content_rating", type="string", example="R"),
     *             @OA\Property(property="is_default", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/UserProfile"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Profile updated successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Profile not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $profile = UserProfile::findOrFail($id);
        
        // Check if the profile belongs to the authenticated user
        if ($profile->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You do not have permission to update this profile',
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:50',
            'is_kids_profile' => 'boolean',
            'content_preferences' => 'nullable|array',
            'content_preferences.*' => 'string',
            'max_content_rating' => 'nullable|string|in:G,PG,PG-13,R,NC-17',
            'is_default' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        // Update profile fields
        if ($request->has('name')) {
            $profile->name = $request->name;
        }
        
        if ($request->has('is_kids_profile')) {
            $profile->is_kids_profile = $request->is_kids_profile;
            
            // If changing to kids profile, ensure appropriate content rating
            if ($request->is_kids_profile && !$request->has('max_content_rating')) {
                $profile->max_content_rating = 'PG';
            }
        }
        
        if ($request->has('content_preferences')) {
            $profile->content_preferences = implode(',', $request->content_preferences);
        }
        
        if ($request->has('max_content_rating')) {
            $profile->max_content_rating = $request->max_content_rating;
        }
        
        // Handle default profile setting
        if ($request->has('is_default') && $request->is_default) {
            // Clear any existing default profile
            $user = $request->user();
            $user->profiles()->where('id', '!=', $profile->id)->update(['is_default' => false]);
            $profile->is_default = true;
        }
        
        $profile->save();
        
        return response()->json([
            'data' => $profile,
            'message' => 'Profile updated successfully',
        ]);
    }
    
    /**
     * Upload a profile avatar.
     * 
     * @OA\Post(
     *     path="/api/profiles/{id}/avatar",
     *     summary="Upload a profile avatar",
     *     tags={"User Profiles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Profile ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="avatar",
     *                     type="string",
     *                     format="binary",
     *                     description="Avatar image file"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Avatar uploaded successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/UserProfile"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Avatar uploaded successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Profile not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function uploadAvatar(Request $request, string $id): JsonResponse
    {
        $profile = UserProfile::findOrFail($id);
        
        // Check if the profile belongs to the authenticated user
        if ($profile->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You do not have permission to update this profile',
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|max:2048',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        // Remove old avatar if exists
        if ($profile->avatar) {
            $oldPath = str_replace('/storage/', '', $profile->avatar);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }
        
        // Store new avatar
        $avatarPath = $request->file('avatar')->store("avatars/profiles/{$profile->user_id}", 'public');
        $profile->avatar = Storage::url($avatarPath);
        $profile->save();
        
        return response()->json([
            'data' => $profile,
            'message' => 'Avatar uploaded successfully',
        ]);
    }
    
    /**
     * Remove the specified profile.
     * 
     * @OA\Delete(
     *     path="/api/profiles/{id}",
     *     summary="Delete a profile",
     *     tags={"User Profiles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Profile ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Profile deleted successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Profile not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $profile = UserProfile::findOrFail($id);
        
        // Check if the profile belongs to the authenticated user
        if ($profile->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You do not have permission to delete this profile',
            ], 403);
        }
        
        // Prevent deletion of the last profile
        $profileCount = $request->user()->profiles()->count();
        if ($profileCount <= 1) {
            return response()->json([
                'message' => 'Cannot delete the only profile. Create another profile first.',
            ], 403);
        }
        
        // If deleting the default profile, set another profile as default
        if ($profile->is_default) {
            $newDefault = $request->user()->profiles()->where('id', '!=', $profile->id)->first();
            $newDefault->is_default = true;
            $newDefault->save();
        }
        
        // Delete avatar if exists
        if ($profile->avatar) {
            $avatarPath = str_replace('/storage/', '', $profile->avatar);
            if (Storage::disk('public')->exists($avatarPath)) {
                Storage::disk('public')->delete($avatarPath);
            }
        }
        
        $profile->delete();
        
        return response()->json([
            'message' => 'Profile deleted successfully',
        ]);
    }
} 