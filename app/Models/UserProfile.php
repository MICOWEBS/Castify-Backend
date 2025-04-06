<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="UserProfile",
 *     title="User Profile",
 *     description="User profile model for multiple profiles per account",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="name", type="string", example="John's Profile"),
 *     @OA\Property(property="avatar", type="string", nullable=true, example="https://example.com/avatars/profile.jpg"),
 *     @OA\Property(property="is_kids_profile", type="boolean", example=false),
 *     @OA\Property(property="content_preferences", type="string", nullable=true, example="action,comedy,drama"),
 *     @OA\Property(property="max_content_rating", type="string", example="PG-13"),
 *     @OA\Property(property="is_default", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class UserProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'avatar',
        'is_kids_profile',
        'content_preferences',
        'max_content_rating',
        'is_default',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_kids_profile' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the user that owns the profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the watch history for the profile.
     */
    public function watchHistory(): HasMany
    {
        return $this->hasMany(WatchHistory::class);
    }
    
    /**
     * Get content preferences as an array.
     */
    public function getContentPreferencesArrayAttribute(): array
    {
        if (empty($this->content_preferences)) {
            return [];
        }
        
        return explode(',', $this->content_preferences);
    }
    
    /**
     * Set content preferences from an array.
     */
    public function setContentPreferencesArrayAttribute(array $preferences): void
    {
        $this->attributes['content_preferences'] = implode(',', $preferences);
    }
} 