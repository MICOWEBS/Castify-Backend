<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\SubtitleController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\WatchHistoryController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\CloudinaryWebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check route - used by Render for health monitoring
Route::get('/health', HealthController::class);

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('user', [AuthController::class, 'user'])->middleware('auth:sanctum');
    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
});

// Video routes
Route::apiResource('videos', VideoController::class);
Route::get('videos/{video}/comments', [CommentController::class, 'forVideo']);

// Subtitle routes
Route::get('videos/{video}/subtitles', [SubtitleController::class, 'index']);
Route::post('videos/{video}/subtitles', [SubtitleController::class, 'store'])->middleware('auth:sanctum');
Route::delete('videos/{video}/subtitles/{language}', [SubtitleController::class, 'destroy'])->middleware('auth:sanctum');
Route::post('videos/{video}/subtitles/generate', [SubtitleController::class, 'generateSubtitles'])->middleware(['auth:sanctum', 'admin']);

// Category routes
Route::apiResource('categories', CategoryController::class);

// Comment routes
Route::apiResource('comments', CommentController::class)->except(['index', 'show', 'update']);

// Analytics routes
Route::prefix('analytics')->middleware(['auth:sanctum'])->group(function () {
    Route::get('videos/{id}', [AnalyticsController::class, 'videoAnalytics']);
    Route::get('user', [AnalyticsController::class, 'userAnalytics']);
    Route::get('dashboard', [AnalyticsController::class, 'dashboard'])->middleware('admin');
});

// Recommendation routes
Route::prefix('recommendations')->group(function () {
    Route::get('/', [RecommendationController::class, 'recommendations'])->middleware('auth:sanctum');
    Route::get('/trending', [RecommendationController::class, 'trending']);
    Route::get('/recently-watched', [RecommendationController::class, 'recentlyWatched'])->middleware('auth:sanctum');
});

// User profile routes
Route::prefix('profiles')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [UserProfileController::class, 'index']);
    Route::post('/', [UserProfileController::class, 'store']);
    Route::get('/{id}', [UserProfileController::class, 'show']);
    Route::patch('/{id}', [UserProfileController::class, 'update']);
    Route::delete('/{id}', [UserProfileController::class, 'destroy']);
    Route::post('/{id}/avatar', [UserProfileController::class, 'uploadAvatar']);
});

// Watch history routes
Route::prefix('watch-history')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [WatchHistoryController::class, 'index']);
    Route::post('/progress', [WatchHistoryController::class, 'updateProgress']);
    Route::get('/continue-watching', [WatchHistoryController::class, 'continueWatching']);
    Route::delete('/{id}', [WatchHistoryController::class, 'destroy']);
    Route::delete('/', [WatchHistoryController::class, 'clearHistory']);
});

// Video playback position
Route::get('videos/{videoId}/playback-position', [WatchHistoryController::class, 'playbackPosition'])
    ->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Email Verification
Route::post('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware(['auth:sanctum', 'signed'])
    ->name('api.verification.verify');
Route::post('/email/resend', [VerificationController::class, 'resend'])
    ->middleware(['auth:sanctum', 'throttle:6,1'])
    ->name('api.verification.resend');

// Password Reset
Route::post('/password/email', [PasswordResetController::class, 'sendResetLinkEmail']);
Route::post('/password/reset', [PasswordResetController::class, 'reset'])->name('api.password.reset');

// User Profile Routes (protected)
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);
    
    // User Profiles (Netflix-style multiple profiles)
    Route::get('/user-profiles', [ProfileController::class, 'index']);
    Route::post('/user-profiles', [ProfileController::class, 'store']);
    Route::put('/user-profiles/{profile}', [ProfileController::class, 'updateProfile']);
    Route::delete('/user-profiles/{profile}', [ProfileController::class, 'destroyProfile']);
    Route::put('/user-profiles/{profile}/make-default', [ProfileController::class, 'makeDefault']);
    
    // Watch History
    Route::get('/watch-history', [ProfileController::class, 'watchHistory']);
});

Route::post('/cloudinary/notify', [CloudinaryWebhookController::class, 'handleNotification']);
