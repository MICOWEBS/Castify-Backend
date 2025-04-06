<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="WatchHistory",
 *     title="Watch History",
 *     description="User watch history for videos with progress tracking",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="video_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="user_profile_id", type="integer", format="int64", nullable=true, example=1),
 *     @OA\Property(property="watched_seconds", type="integer", example=600),
 *     @OA\Property(property="video_duration", type="integer", example=3600),
 *     @OA\Property(property="completed", type="boolean", example=false),
 *     @OA\Property(property="last_watched_at", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class WatchHistory extends Model
{
    use HasFactory;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'watch_history';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'video_id',
        'user_profile_id',
        'watched_seconds',
        'video_duration',
        'completed',
        'last_watched_at',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'watched_seconds' => 'integer',
        'video_duration' => 'integer',
        'completed' => 'boolean',
        'last_watched_at' => 'datetime',
    ];
    
    /**
     * Get the user that owns the watch history.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the video for this watch history.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
    
    /**
     * Get the profile for this watch history.
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(UserProfile::class, 'user_profile_id');
    }
    
    /**
     * Get the watch progress as a percentage.
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->video_duration <= 0) {
            return 0;
        }
        
        return min(100, round(($this->watched_seconds / $this->video_duration) * 100));
    }
    
    /**
     * Determine if the video can be resumed (between 5% and 95% completion).
     */
    public function getCanResumeAttribute(): bool
    {
        $percentage = $this->progress_percentage;
        return $percentage >= 5 && $percentage <= 95;
    }
} 