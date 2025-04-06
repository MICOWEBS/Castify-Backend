<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CloudinaryService
{
    protected Cloudinary $cloudinary;

    public function __construct(Cloudinary $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    /**
     * Upload a video to Cloudinary
     *
     * @param string $filePath The path to the video file
     * @param string $publicId Optional public ID for the uploaded video
     * @param array $options Additional upload options
     * @return array The upload response
     */
    public function uploadVideo(string $filePath, ?string $publicId = null, array $options = []): array
    {
        try {
            $uploadOptions = array_merge([
                'resource_type' => 'video',
                'public_id' => $publicId ?? Str::uuid()->toString(),
                'overwrite' => true,
                'folder' => 'videos',
                'streaming_profile' => 'hd',
                'eager' => [
                    ['format' => 'mp4', 'transformation' => ['quality' => 'auto']],
                    ['format' => 'webm', 'transformation' => ['quality' => 'auto']]
                ],
                'eager_async' => true,
                'eager_notification_url' => config('app.url') . '/api/cloudinary/notify',
                'notification_url' => config('app.url') . '/api/cloudinary/notify'
            ], $options);

            return $this->cloudinary->uploadApi()->upload($filePath, $uploadOptions);
        } catch (\Exception $e) {
            Log::error('Cloudinary upload error: ' . $e->getMessage(), [
                'file' => $filePath,
                'options' => $options
            ]);
            throw $e;
        }
    }

    /**
     * Generate a thumbnail from a video
     *
     * @param string $publicId The public ID of the video
     * @param int $width Thumbnail width
     * @param int $height Thumbnail height
     * @param int $timeOffset Time offset in seconds for the thumbnail
     * @return string The thumbnail URL
     */
    public function generateVideoThumbnail(string $publicId, int $width = 640, int $height = 360, int $timeOffset = 5): string
    {
        return $this->cloudinary->video($publicId)
            ->resize("w_$width,h_$height,c_fill")
            ->roundCorners(10)
            ->frame(1)
            ->splice("so_$timeOffset")
            ->format('jpg')
            ->quality('auto')
            ->toUrl();
    }

    /**
     * Get streaming URLs for a video
     *
     * @param string $publicId The public ID of the video
     * @return array The streaming URLs for different formats
     */
    public function getStreamingUrls(string $publicId): array
    {
        return [
            'dash' => $this->cloudinary->video($publicId)
                ->delivery('stream')
                ->format('m3u8')
                ->toUrl(),
            'hls' => $this->cloudinary->video($publicId)
                ->delivery('stream')
                ->format('mpd')
                ->toUrl(),
            'mp4' => $this->cloudinary->video($publicId)
                ->format('mp4')
                ->quality('auto')
                ->toUrl(),
        ];
    }

    /**
     * Delete a resource from Cloudinary
     *
     * @param string $publicId The public ID of the resource
     * @param string $resourceType The type of resource (image, video, raw)
     * @return array The deletion response
     */
    public function deleteResource(string $publicId, string $resourceType = 'video'): array
    {
        try {
            return $this->cloudinary->uploadApi()->destroy($publicId, [
                'resource_type' => $resourceType
            ]);
        } catch (\Exception $e) {
            Log::error('Cloudinary delete error: ' . $e->getMessage(), [
                'publicId' => $publicId,
                'resourceType' => $resourceType
            ]);
            throw $e;
        }
    }

    /**
     * Get video information and metadata
     *
     * @param string $publicId The public ID of the video
     * @return array The video details
     */
    public function getVideoInfo(string $publicId): array
    {
        try {
            return $this->cloudinary->adminApi()->asset($publicId, [
                'resource_type' => 'video',
                'image_metadata' => true,
                'video_metadata' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Cloudinary info retrieval error: ' . $e->getMessage(), [
                'publicId' => $publicId
            ]);
            throw $e;
        }
    }

    /**
     * Create a signed upload URL for direct browser uploads
     *
     * @param array $options Upload parameters and options
     * @return array The signed upload params
     */
    public function createUploadSignature(array $options = []): array
    {
        $timestamp = time();
        $defaults = [
            'timestamp' => $timestamp,
            'folder' => 'videos',
            'resource_type' => 'video',
            'streaming_profile' => 'hd'
        ];
        
        $params = array_merge($defaults, $options);
        
        // Get Cloudinary configuration
        $config = $this->cloudinary->configuration();
        $apiSecret = $config->cloud->apiSecret;
        
        // Generate signature
        $to_sign = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $to_sign[] = $key . '=' . $value;
        }
        
        $to_sign = implode('&', $to_sign);
        $signature = hash('sha256', $to_sign . $apiSecret);
        
        return [
            'signature' => $signature,
            'api_key' => $config->cloud->apiKey,
            'params' => $params
        ];
    }
} 