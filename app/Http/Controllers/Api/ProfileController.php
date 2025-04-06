<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's information.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified' => $user->hasVerifiedEmail(),
                'created_at' => $user->created_at,
            ]
        ]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if ($request->has('first_name')) {
            $user->first_name = $request->first_name;
        }

        if ($request->has('last_name')) {
            $user->last_name = $request->last_name;
        }

        if ($request->has('first_name') || $request->has('last_name')) {
            $user->name = $user->first_name . ' ' . $user->last_name;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified' => $user->hasVerifiedEmail(),
            ]
        ]);
    }

    /**
     * Delete the authenticated user's account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $user = $request->user();
        
        // Revoke all tokens
        $user->tokens()->delete();
        
        // Delete user
        $user->delete();

        return response()->json([
            'message' => 'User account deleted successfully'
        ]);
    }

    /**
     * Get the authenticated user's Netflix-style profiles.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $profiles = $request->user()->profiles;
        
        return response()->json([
            'profiles' => $profiles
        ]);
    }

    /**
     * Create a new profile for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'is_default' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = $request->user();
        
        // Check profile limit (Netflix allows 5 profiles)
        if ($user->profiles->count() >= 5) {
            return response()->json([
                'message' => 'You have reached the maximum number of profiles allowed (5)'
            ], 400);
        }
        
        // If this is the first profile or user wants it as default
        $isDefault = $user->profiles->count() === 0 || $request->is_default;
        
        // If setting as default, make all other profiles non-default
        if ($isDefault) {
            $user->profiles()->update(['is_default' => false]);
        }
        
        $profile = $user->profiles()->create([
            'name' => $request->name,
            'is_default' => $isDefault,
        ]);

        return response()->json([
            'message' => 'Profile created successfully',
            'profile' => $profile
        ], 201);
    }

    /**
     * Update a specific profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $profile
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request, $profile)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $profile = UserProfile::findOrFail($profile);
        
        // Check ownership
        if ($profile->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        if ($request->has('name')) {
            $profile->name = $request->name;
        }
        
        $profile->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => $profile
        ]);
    }

    /**
     * Delete a specific profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $profile
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyProfile(Request $request, $profile)
    {
        $profile = UserProfile::findOrFail($profile);
        
        // Check ownership
        if ($profile->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        // Cannot delete default profile
        if ($profile->is_default) {
            return response()->json([
                'message' => 'Cannot delete the default profile'
            ], 400);
        }
        
        $profile->delete();

        return response()->json([
            'message' => 'Profile deleted successfully'
        ]);
    }

    /**
     * Make a profile the default.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $profile
     * @return \Illuminate\Http\JsonResponse
     */
    public function makeDefault(Request $request, $profile)
    {
        $profile = UserProfile::findOrFail($profile);
        
        // Check ownership
        if ($profile->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        // Already default
        if ($profile->is_default) {
            return response()->json([
                'message' => 'This profile is already the default'
            ]);
        }
        
        // Make all other profiles non-default
        $request->user()->profiles()->update(['is_default' => false]);
        
        // Make this profile default
        $profile->is_default = true;
        $profile->save();

        return response()->json([
            'message' => 'Profile set as default',
            'profile' => $profile
        ]);
    }

    /**
     * Get the authenticated user's watch history.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function watchHistory(Request $request)
    {
        $watchHistory = $request->user()->watchHistory()
            ->with('video')
            ->latest()
            ->paginate(20);
            
        return response()->json([
            'watch_history' => $watchHistory
        ]);
    }
} 