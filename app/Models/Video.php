<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;

/**
 * @OA\Schema(
 *     schema="Video",
 *     title="Video",
 *     description="Video model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="Introduction to Laravel"),
 *     @OA\Property(property="description", type="string", example="Learn the basics of Laravel framework"),
 *     @OA\Property(property="url", type="string", example="https://example.com/videos/laravel-intro.mp4"),
 *     @OA\Property(property="thumbnail", type="string", example="https://example.com/thumbnails/laravel-intro.jpg"),
 *     @OA\Property(property="status", type="string", enum={"pending", "processing", "published", "failed"}, example="published"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="categories",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Category")
 *     )
 * )
 */
class Video extends Model
{
    use HasFactory, Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'url',
        'thumbnail',
        'status',
        'cloudinary_id',
        'cloudinary_version',
        'streaming_url',
        'format',
        'duration',
        'file_size',
        'width',
        'height',
        'file_name',
        'error_message',
        'processed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'view_count' => 'integer',
            'duration' => 'float',
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $array = $this->toArray();
        
        // Add related categories to the searchable array
        $array['categories'] = $this->categories->pluck('name')->toArray();
        
        return $array;
    }

    /**
     * Get the user that owns the video.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the video's metrics.
     */
    public function metrics(): HasOne
    {
        return $this->hasOne(VideoMetric::class);
    }

    /**
     * Get the video's categories.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Get the video's comments.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Increment the view count.
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
        
        if ($this->metrics) {
            $this->metrics->increment('views');
        }
    }

    /**
     * Filter videos by category.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $categoryId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInCategory($query, $categoryId)
    {
        return $query->whereHas('categories', function ($query) use ($categoryId) {
            $query->where('categories.id', $categoryId);
        });
    }

    /**
     * Get the video's streaming URLs from Cloudinary.
     *
     * @return array
     */
    public function getStreamingUrls(): array
    {
        if (!$this->cloudinary_id) {
            return [
                'mp4' => $this->url,
                'hls' => null,
                'dash' => null,
            ];
        }

        $cloudinary = app(\Cloudinary\Cloudinary::class);
        
        return [
            'dash' => $cloudinary->video($this->cloudinary_id)
                ->delivery('stream')
                ->format('mpd')
                ->toUrl(),
            'hls' => $cloudinary->video($this->cloudinary_id)
                ->delivery('stream')
                ->format('m3u8')
                ->toUrl(),
            'mp4' => $cloudinary->video($this->cloudinary_id)
                ->format('mp4')
                ->quality('auto')
                ->toUrl(),
        ];
    }

    /**
     * Get the video's thumbnail URL from Cloudinary.
     *
     * @param int $width
     * @param int $height
     * @return string
     */
    public function getThumbnailUrl(int $width = 640, int $height = 360): string
    {
        if (!$this->cloudinary_id) {
            return $this->thumbnail ?? '';
        }

        $cloudinary = app(\Cloudinary\Cloudinary::class);
        
        return $cloudinary->video($this->cloudinary_id)
            ->resize("w_$width,h_$height,c_fill")
            ->frame(1)
            ->quality('auto')
            ->format('jpg')
            ->toUrl();
    }

    /**
     * Determine if the video is ready for playback.
     *
     * @return bool
     */
    public function isReady(): bool
    {
        return $this->status === 'complete';
    }

    /**
     * Determine if the video processing has failed.
     *
     * @return bool
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }
}
