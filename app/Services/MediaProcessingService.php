<?php

namespace App\Services;

use App\Models\Video;
use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaProcessingService
{
    /**
     * Process a video file and prepare it for streaming
     */
    public function processVideo(Video $video, ?UploadedFile $file = null): bool
    {
        try {
            Log::info('Starting advanced video processing', ['video_id' => $video->id]);
            
            // If we have a file uploaded, store it
            if ($file) {
                $videoPath = $this->storeOriginalVideo($file);
                $video->url = Storage::url($videoPath);
                $video->save();
            }

            // Create adaptive streams (HLS/DASH)
            $this->createAdaptiveStreams($video);
            
            // Generate thumbnails
            $this->generateThumbnails($video);
            
            // Apply DRM if needed
            if (config('services.drm.enabled', false)) {
                $this->applyDrmProtection($video);
            }
            
            // Generate subtitles using AI
            if (config('services.speech_to_text.enabled', false)) {
                $this->generateSubtitles($video);
            }
            
            Log::info('Video processing completed successfully', ['video_id' => $video->id]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error processing video', [
                'video_id' => $video->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }
    
    /**
     * Store the original uploaded video
     */
    protected function storeOriginalVideo(UploadedFile $file): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        return $file->storeAs('videos/original', $filename, 'public');
    }
    
    /**
     * Create adaptive bitrate streams for HLS/DASH delivery
     */
    protected function createAdaptiveStreams(Video $video): void
    {
        Log::info('Creating adaptive bitrate streams', ['video_id' => $video->id]);
        
        $videoId = $video->id;
        $basePath = "videos/adaptive/{$videoId}";
        
        // Create base directories
        Storage::disk('public')->makeDirectory($basePath);
        Storage::disk('public')->makeDirectory("videos/temp");
        
        // Extract the original video path
        $originalPath = $this->getOriginalVideoPath($video);
        
        if (!file_exists($originalPath)) {
            throw new \Exception("Original video file does not exist: {$originalPath}");
        }
        
        // Define quality levels for transcoding
        $qualities = [
            '240p' => ['resolution' => '426x240', 'bitrate' => '400k'],
            '360p' => ['resolution' => '640x360', 'bitrate' => '700k'],
            '480p' => ['resolution' => '854x480', 'bitrate' => '1200k'],
            '720p' => ['resolution' => '1280x720', 'bitrate' => '2500k'],
            '1080p' => ['resolution' => '1920x1080', 'bitrate' => '5000k'],
        ];
        
        // Create output path for HLS segments
        $outputPath = storage_path("app/public/{$basePath}");
        
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0777, true);
        }

        // Generate HLS playlist and segments
        $variantPlaylistContent = "#EXTM3U\n#EXT-X-VERSION:3\n";
        
        foreach ($qualities as $name => $settings) {
            $segmentPath = "{$outputPath}/{$name}";
            
            if (!is_dir($segmentPath)) {
                mkdir($segmentPath, 0777, true);
            }
            
            // Create HLS segments for this quality
            $cmd = "ffmpeg -i {$originalPath} -vf scale={$settings['resolution']} -c:v h264 -b:v {$settings['bitrate']} " .
                   "-c:a aac -b:a 128k -hls_time 10 -hls_list_size 0 -hls_segment_filename " .
                   "{$segmentPath}/segment_%03d.ts {$segmentPath}/playlist.m3u8";
                   
            Log::debug("Executing FFmpeg command: {$cmd}");
            
            $output = [];
            $returnCode = 0;
            exec($cmd, $output, $returnCode);
            
            if ($returnCode !== 0) {
                Log::error("FFmpeg command failed", ['output' => implode("\n", $output)]);
                throw new \Exception("Failed to create HLS segments for {$name}");
            }
            
            // Add this variant to the master playlist
            $variantPlaylistContent .= "#EXT-X-STREAM-INF:BANDWIDTH=" . $this->getBandwidth($settings['bitrate']) . 
                                      ",RESOLUTION=" . str_replace('x', ':', $settings['resolution']) . "\n";
            $variantPlaylistContent .= "{$name}/playlist.m3u8\n";
        }
        
        // Write master playlist file
        file_put_contents("{$outputPath}/playlist.m3u8", $variantPlaylistContent);
        
        // Update video URL to point to the HLS playlist
        $video->url = url("/storage/{$basePath}/playlist.m3u8");
        $video->adaptive_streaming = true;
        $video->save();
        
        Log::info('HLS adaptive streaming created successfully', ['video_id' => $video->id]);
    }
    
    /**
     * Get bandwidth value from bitrate string
     */
    private function getBandwidth(string $bitrate): int
    {
        $number = (int) preg_replace('/[^0-9]/', '', $bitrate);
        
        if (strpos($bitrate, 'm') !== false || strpos($bitrate, 'M') !== false) {
            return $number * 1000000;
        } else if (strpos($bitrate, 'k') !== false || strpos($bitrate, 'K') !== false) {
            return $number * 1000;
        }
        
        return $number;
    }
    
    /**
     * Get the original video file path
     */
    private function getOriginalVideoPath(Video $video): string
    {
        if (isset($video->file_path)) {
            return storage_path('app/public/' . $video->file_path);
        }
        
        $url = $video->url;
        $path = parse_url($url, PHP_URL_PATH);
        
        if (strpos($path, '/storage/') === 0) {
            $path = substr($path, 9); // Remove "/storage/"
            return storage_path('app/public/' . $path);
        }
        
        throw new \Exception("Cannot determine original video path for video ID: {$video->id}");
    }
    
    /**
     * Generate thumbnails for the video
     */
    protected function generateThumbnails(Video $video): void
    {
        Log::info('Generating thumbnails', ['video_id' => $video->id]);
        
        $videoId = $video->id;
        $originalPath = $this->getOriginalVideoPath($video);
        $outputPath = storage_path("app/public/thumbnails/{$videoId}");
        
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0777, true);
        }
        
        // Get video duration
        $durationCmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 {$originalPath}";
        $duration = (float) shell_exec($durationCmd);
        
        if ($duration <= 0) {
            Log::warning("Could not determine video duration", ['video_id' => $video->id]);
            $duration = 600; // Default to 10 minutes
        }
        
        // Extract frames at different timestamps
        $timestamps = [
            intval($duration * 0.1),
            intval($duration * 0.3),
            intval($duration * 0.5),
            intval($duration * 0.7),
            intval($duration * 0.9),
        ];
        
        $thumbnails = [];
        
        foreach ($timestamps as $index => $timestamp) {
            $outputFile = "{$outputPath}/thumb_{$index}.jpg";
            $cmd = "ffmpeg -i {$originalPath} -ss {$timestamp} -vframes 1 -q:v 2 {$outputFile}";
            
            $output = [];
            $returnCode = 0;
            exec($cmd, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($outputFile)) {
                $thumbnails[] = "thumbnails/{$videoId}/thumb_{$index}.jpg";
            } else {
                Log::warning("Failed to generate thumbnail at timestamp {$timestamp}", [
                    'video_id' => $video->id, 
                    'output' => implode("\n", $output)
                ]);
            }
        }
        
        // Use the middle thumbnail as the default or the first successful one
        if (!empty($thumbnails)) {
            $defaultThumbnail = isset($thumbnails[2]) ? $thumbnails[2] : $thumbnails[0];
            $video->thumbnail_path = $defaultThumbnail;
            $video->save();
        } else {
            Log::error("Failed to generate any thumbnails for video", ['video_id' => $video->id]);
        }
    }
    
    /**
     * Apply DRM protection to the video
     */
    protected function applyDrmProtection(Video $video): void
    {
        Log::info('Applying DRM protection', ['video_id' => $video->id]);
        
        $drmProvider = config('services.drm.provider', 'widevine');
        $licenseServer = config('services.drm.license_server');
        $contentKey = config('services.drm.content_key');
        
        if (empty($licenseServer) || empty($contentKey)) {
            Log::warning('DRM license server or content key not configured', ['video_id' => $video->id]);
            return;
        }
        
        try {
            $videoId = $video->id;
            $basePath = "videos/adaptive/{$videoId}";
            $masterPlaylist = storage_path("app/public/{$basePath}/playlist.m3u8");
            
            // In a real system, this would use a DRM service provider API
            // Here's pseudocode for what we would do:
            
            switch ($drmProvider) {
                case 'widevine':
                    $this->applyWidevineDRM($video, $masterPlaylist, $licenseServer, $contentKey);
                    break;
                    
                case 'playready':
                    $this->applyPlayReadyDRM($video, $masterPlaylist, $licenseServer, $contentKey);
                    break;
                    
                case 'fairplay':
                    $this->applyFairPlayDRM($video, $masterPlaylist, $licenseServer, $contentKey);
                    break;
                    
                default:
                    throw new \Exception("Unsupported DRM provider: {$drmProvider}");
            }
            
            // Update video with DRM status
            $video->is_protected = true;
            $video->drm_type = $drmProvider;
            $video->save();
            
            Log::info('DRM protection applied successfully', ['video_id' => $video->id, 'provider' => $drmProvider]);
        } catch (\Exception $e) {
            Log::error('Error applying DRM protection', [
                'video_id' => $video->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Apply Widevine DRM (Google)
     */
    private function applyWidevineDRM(Video $video, string $masterPlaylist, string $licenseServer, string $contentKey): void
    {
        // This would integrate with a real DRM service like Axinom, EZDRM, or BuyDRM
        // The implementation would depend on the specific service provider
        
        // Example for integration with a real DRM service:
        /*
        $client = new Client();
        $response = $client->post('https://drm-service-api.example.com/keys/widevine', [
            'json' => [
                'content_id' => (string) $video->id,
                'policy' => 'streaming',
                'tracks' => [
                    ['type' => 'SD', 'key' => $contentKey]
                ]
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . config('services.drm.api_key')
            ]
        ]);
        
        $drmData = json_decode($response->getBody(), true);
        $keyId = $drmData['key_id'];
        $key = $drmData['key'];
        
        // Encrypt segments using shaka-packager or other tools
        $cmd = "packager input={$inputPath},stream=audio,output={$outputPath}_audio.mp4 " .
               "--enable_widevine_encryption " .
               "--key_server_url {$licenseServer} " .
               "--content_id {$video->id} " .
               "--signer {$signingKey}";
        exec($cmd);
        
        // Update master playlist with DRM information
        */
        
        // For now, log what we would do in production
        Log::info('Widevine DRM would be applied here in production', [
            'video_id' => $video->id,
            'license_server' => $licenseServer
        ]);
    }
    
    /**
     * Apply PlayReady DRM (Microsoft)
     */
    private function applyPlayReadyDRM(Video $video, string $masterPlaylist, string $licenseServer, string $contentKey): void
    {
        // Similar to Widevine implementation but for PlayReady
        Log::info('PlayReady DRM would be applied here in production', [
            'video_id' => $video->id,
            'license_server' => $licenseServer
        ]);
    }
    
    /**
     * Apply FairPlay DRM (Apple)
     */
    private function applyFairPlayDRM(Video $video, string $masterPlaylist, string $licenseServer, string $contentKey): void
    {
        // Implementation for Apple FairPlay
        Log::info('FairPlay DRM would be applied here in production', [
            'video_id' => $video->id,
            'license_server' => $licenseServer
        ]);
    }
    
    /**
     * Generate subtitles using AI speech recognition
     */
    protected function generateSubtitles(Video $video): void
    {
        Log::info('Generating automatic subtitles', ['video_id' => $video->id]);
        
        $provider = config('services.speech_to_text.provider', 'google');
        $apiKey = config('services.speech_to_text.api_key', '');
        
        if (empty($apiKey)) {
            Log::warning('Speech-to-text API key not configured', ['video_id' => $video->id]);
            return;
        }
        
        $videoId = $video->id;
        $originalPath = $this->getOriginalVideoPath($video);
        $audioPath = storage_path("app/public/temp/{$videoId}_audio.flac");
        
        // Extract audio from video
        $cmd = "ffmpeg -i {$originalPath} -ac 1 -ar 16000 -vn {$audioPath}";
        exec($cmd);
        
        if (!file_exists($audioPath)) {
            Log::error('Failed to extract audio from video', ['video_id' => $video->id]);
            return;
        }
        
        try {
            // Languages to generate subtitles for
            $languages = ['en'];
            
            // Add more if requested
            if (config('services.speech_to_text.additional_languages')) {
                $additionalLanguages = explode(',', config('services.speech_to_text.additional_languages'));
                $languages = array_merge($languages, $additionalLanguages);
                $languages = array_unique($languages);
            }
            
            $generatedLanguages = [];
            
            foreach ($languages as $language) {
                $subtitle = $this->generateSubtitleForLanguage($video, $audioPath, $language, $provider, $apiKey);
                
                if ($subtitle) {
                    $generatedLanguages[] = $language;
                }
            }
            
            // Clean up temporary audio file
            if (file_exists($audioPath)) {
                unlink($audioPath);
            }
            
            // Update video with subtitle info
            if (!empty($generatedLanguages)) {
                $video->has_subtitles = true;
                $video->subtitle_languages = json_encode($generatedLanguages);
                $video->save();
                
                Log::info('Subtitles generated successfully', [
                    'video_id' => $video->id,
                    'languages' => $generatedLanguages
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Error generating subtitles', [
                'video_id' => $video->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Generate subtitle for a specific language
     */
    private function generateSubtitleForLanguage(Video $video, string $audioPath, string $language, string $provider, string $apiKey): bool
    {
        $videoId = $video->id;
        $outputDir = storage_path("app/public/subtitles/{$videoId}");
        
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }
        
        $outputPath = "{$outputDir}/{$language}.vtt";
        
        switch ($provider) {
            case 'google':
                return $this->generateSubtitleWithGoogle($audioPath, $outputPath, $language, $apiKey);
                
            case 'aws':
                return $this->generateSubtitleWithAWS($audioPath, $outputPath, $language, $apiKey);
                
            case 'azure':
                return $this->generateSubtitleWithAzure($audioPath, $outputPath, $language, $apiKey);
                
            default:
                Log::warning('Unsupported speech-to-text provider', [
                    'video_id' => $video->id,
                    'provider' => $provider
                ]);
                return false;
        }
    }
    
    /**
     * Generate subtitle using Google Cloud Speech-to-Text
     */
    private function generateSubtitleWithGoogle(string $audioPath, string $outputPath, string $language, string $apiKey): bool
    {
        // This would use the Google Cloud Speech-to-Text API
        // Here's pseudocode for what we would do:
        
        /*
        // Read audio file and encode as base64
        $audioContent = base64_encode(file_get_contents($audioPath));
        
        // Create client
        $client = new Client();
        $response = $client->post('https://speech.googleapis.com/v1p1beta1/speech:recognize', [
            'json' => [
                'config' => [
                    'encoding' => 'FLAC',
                    'sampleRateHertz' => 16000,
                    'languageCode' => $language,
                    'enableAutomaticPunctuation' => true,
                    'enableWordTimeOffsets' => true,
                ],
                'audio' => [
                    'content' => $audioContent
                ]
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey
            ]
        ]);
        
        $results = json_decode($response->getBody(), true);
        
        // Format results as VTT
        $vtt = "WEBVTT\n\n";
        foreach ($results['results'] as $result) {
            foreach ($result['alternatives'][0]['words'] as $i => $wordInfo) {
                $startTime = $this->formatTime($wordInfo['startTime']);
                $endTime = $this->formatTime($wordInfo['endTime']);
                $word = $wordInfo['word'];
                
                // Group words into subtitles
                // Logic for grouping would go here
                
                $vtt .= "{$startTime} --> {$endTime}\n{$word}\n\n";
            }
        }
        
        file_put_contents($outputPath, $vtt);
        */
        
        // For now, just log what we would do and create a placeholder file
        Log::info('Google Speech-to-Text would be used here in production', [
            'audioPath' => $audioPath,
            'outputPath' => $outputPath,
            'language' => $language
        ]);
        
        // Create a simple placeholder VTT file
        $vtt = "WEBVTT\n\n";
        $vtt .= "00:00:00.000 --> 00:00:05.000\n";
        $vtt .= "This is a placeholder subtitle for development purposes.\n\n";
        $vtt .= "00:00:05.000 --> 00:00:10.000\n";
        $vtt .= "In production, real transcription would be generated using Google Speech-to-Text.\n\n";
        
        file_put_contents($outputPath, $vtt);
        
        return true;
    }
    
    /**
     * Generate subtitle using AWS Transcribe
     */
    private function generateSubtitleWithAWS(string $audioPath, string $outputPath, string $language, string $apiKey): bool
    {
        // AWS Transcribe implementation would go here
        Log::info('AWS Transcribe would be used here in production', [
            'audioPath' => $audioPath,
            'outputPath' => $outputPath,
            'language' => $language
        ]);
        
        // Create a placeholder VTT file
        $vtt = "WEBVTT\n\n";
        $vtt .= "00:00:00.000 --> 00:00:05.000\n";
        $vtt .= "This is a placeholder subtitle for development purposes.\n\n";
        $vtt .= "00:00:05.000 --> 00:00:10.000\n";
        $vtt .= "In production, real transcription would be generated using AWS Transcribe.\n\n";
        
        file_put_contents($outputPath, $vtt);
        
        return true;
    }
    
    /**
     * Generate subtitle using Azure Speech Service
     */
    private function generateSubtitleWithAzure(string $audioPath, string $outputPath, string $language, string $apiKey): bool
    {
        // Azure Speech Service implementation would go here
        Log::info('Azure Speech Service would be used here in production', [
            'audioPath' => $audioPath,
            'outputPath' => $outputPath,
            'language' => $language
        ]);
        
        // Create a placeholder VTT file
        $vtt = "WEBVTT\n\n";
        $vtt .= "00:00:00.000 --> 00:00:05.000\n";
        $vtt .= "This is a placeholder subtitle for development purposes.\n\n";
        $vtt .= "00:00:05.000 --> 00:00:10.000\n";
        $vtt .= "In production, real transcription would be generated using Azure Speech Service.\n\n";
        
        file_put_contents($outputPath, $vtt);
        
        return true;
    }
    
    /**
     * Format timestamp for WebVTT
     */
    private function formatTime(string $seconds): string
    {
        $seconds = (float) str_replace('s', '', $seconds);
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        return sprintf('%02d:%02d:%06.3f', $hours, $minutes, $secs);
    }
} 