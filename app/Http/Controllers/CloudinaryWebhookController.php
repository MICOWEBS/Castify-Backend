<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CloudinaryWebhookController extends Controller
{
    /**
     * Handle Cloudinary webhook notifications
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function handleNotification(Request $request)
    {
        // Log the incoming webhook data
        Log::info('Cloudinary webhook received', $request->all());

        $data = $request->all();
        
        // Validate that this is a genuine Cloudinary webhook
        if (!$this->validateWebhook($request)) {
            Log::warning('Invalid Cloudinary webhook signature', [
                'headers' => $request->headers->all(),
                'ip' => $request->ip()
            ]);
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        // Extract the notification type and details
        $notificationType = $data['notification_type'] ?? '';
        $resourceType = $data['resource_type'] ?? '';
        $publicId = $data['public_id'] ?? '';
        
        // Find the associated video by public_id (stored in cloudinary_id)
        $video = Video::where('cloudinary_id', $publicId)->first();
        
        if (!$video) {
            Log::warning('Video not found for Cloudinary notification', [
                'public_id' => $publicId,
                'notification_type' => $notificationType
            ]);
            return response()->json(['status' => 'error', 'message' => 'Video not found'], 404);
        }

        // Handle different notification types
        switch ($notificationType) {
            case 'upload':
                return $this->handleUploadNotification($video, $data);
                
            case 'eager':
                return $this->handleEagerNotification($video, $data);
            
            case 'error':
                return $this->handleErrorNotification($video, $data);
                
            default:
                Log::info('Unhandled Cloudinary notification type', [
                    'type' => $notificationType,
                    'data' => $data
                ]);
                return response()->json(['status' => 'success', 'message' => 'Notification acknowledged']);
        }
    }
    
    /**
     * Handle upload complete notification
     *
     * @param Video $video
     * @param array $data
     * @return \Illuminate\Http\Response
     */
    private function handleUploadNotification(Video $video, array $data)
    {
        // Update the video with Cloudinary metadata
        $video->update([
            'cloudinary_version' => $data['version'] ?? null,
            'format' => $data['format'] ?? $video->format,
            'duration' => $data['duration'] ?? $video->duration,
            'file_size' => $data['bytes'] ?? $video->file_size,
            'width' => $data['width'] ?? $video->width,
            'height' => $data['height'] ?? $video->height,
            'status' => 'processing' // Still processing eager transformations
        ]);
        
        // Delete the local file if it exists (because now it's in Cloudinary)
        if (Storage::disk('local')->exists('uploads/videos/' . $video->file_name)) {
            Storage::disk('local')->delete('uploads/videos/' . $video->file_name);
        }
        
        return response()->json(['status' => 'success', 'message' => 'Upload notification processed']);
    }
    
    /**
     * Handle eager transformation complete notification
     *
     * @param Video $video
     * @param array $data
     * @return \Illuminate\Http\Response
     */
    private function handleEagerNotification(Video $video, array $data)
    {
        // All transformations are complete, video is ready to stream
        $video->update([
            'status' => 'complete',
            'streaming_url' => $data['secure_url'] ?? $video->streaming_url,
            'processed_at' => now()
        ]);
        
        return response()->json(['status' => 'success', 'message' => 'Eager notification processed']);
    }
    
    /**
     * Handle error notification
     *
     * @param Video $video
     * @param array $data
     * @return \Illuminate\Http\Response
     */
    private function handleErrorNotification(Video $video, array $data)
    {
        // Log the error
        Log::error('Cloudinary processing error', [
            'video_id' => $video->id,
            'error' => $data['error'] ?? 'Unknown error',
            'data' => $data
        ]);
        
        // Update the video status
        $video->update([
            'status' => 'failed',
            'error_message' => $data['error']['message'] ?? 'Processing failed'
        ]);
        
        return response()->json(['status' => 'success', 'message' => 'Error notification processed']);
    }
    
    /**
     * Validate the webhook signature
     *
     * @param Request $request
     * @return bool
     */
    private function validateWebhook(Request $request): bool
    {
        // In a production environment, you should validate the webhook
        // using the signature provided by Cloudinary
        // For simplicity, we're just checking for some expected parameters
        
        $required = ['notification_type', 'timestamp'];
        foreach ($required as $param) {
            if (!$request->has($param)) {
                return false;
            }
        }
        
        return true;
    }
} 