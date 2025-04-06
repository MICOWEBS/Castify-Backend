<?php

namespace Tests\Unit;

use App\Jobs\ProcessVideoJob;
use App\Models\User;
use App\Models\Video;
use App\Notifications\VideoProcessingFailed;
use App\Services\MediaProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VideoProcessingTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * Test video processing job dispatch.
     */
    public function test_video_upload_dispatches_processing_job(): void
    {
        Queue::fake();
        
        $user = User::factory()->create(['is_verified' => true]);
        
        $this->actingAs($user);
        
        // Create a new video
        $video = Video::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        
        // Verify the job was dispatched
        Queue::assertPushed(ProcessVideoJob::class, function ($job) use ($video) {
            return $job->video->id === $video->id;
        });
    }
    
    /**
     * Test video processing success.
     */
    public function test_video_processing_success(): void
    {
        // Create a mock of the MediaProcessingService
        $mediaService = $this->createMock(MediaProcessingService::class);
        
        // Set up the mock to return true for processVideo
        $mediaService->method('processVideo')
            ->willReturn(true);
        
        // Create a user and video
        $user = User::factory()->create();
        $video = Video::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        
        // Create the job and call handle with our mock
        $job = new ProcessVideoJob($video);
        $job->handle($mediaService);
        
        // Refresh the video from database
        $video->refresh();
        
        // Assert video status is complete
        $this->assertEquals('complete', $video->status);
    }
    
    /**
     * Test video processing failure.
     */
    public function test_video_processing_failure(): void
    {
        Notification::fake();
        
        // Create a mock of the MediaProcessingService
        $mediaService = $this->createMock(MediaProcessingService::class);
        
        // Set up the mock to return false for processVideo
        $mediaService->method('processVideo')
            ->willReturn(false);
        
        // Create a user and video
        $user = User::factory()->create();
        $video = Video::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        
        // Create the job
        $job = new ProcessVideoJob($video);
        
        // Mock the attempts and release methods
        $job->attempts = function () {
            return 3; // Simulate final attempt
        };
        
        $job->release = function () {
            // Do nothing
        };
        
        // Call handle with our mock
        $job->handle($mediaService);
        
        // Refresh the video from database
        $video->refresh();
        
        // Assert video status is failed
        $this->assertEquals('failed', $video->status);
        
        // Assert a notification was sent
        Notification::assertSentTo(
            $user,
            VideoProcessingFailed::class,
            function ($notification, $channels) use ($video) {
                return $notification->video->id === $video->id;
            }
        );
    }
    
    /**
     * Test video retry logic.
     */
    public function test_video_processing_retry_logic(): void
    {
        // Create a mock of the MediaProcessingService
        $mediaService = $this->createMock(MediaProcessingService::class);
        
        // Set up the mock to return false for processVideo
        $mediaService->method('processVideo')
            ->willReturn(false);
        
        // Create a user and video
        $user = User::factory()->create();
        $video = Video::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        
        // Create the job
        $job = new ProcessVideoJob($video);
        
        // Mock the attempts and release methods
        $job->attempts = function () {
            return 1; // First attempt
        };
        
        $released = false;
        $job->release = function () use (&$released) {
            $released = true;
        };
        
        // Call handle with our mock
        $job->handle($mediaService);
        
        // Refresh the video from database
        $video->refresh();
        
        // Assert video status is still pending for retry
        $this->assertEquals('pending', $video->status);
        
        // Assert the job was released for retry
        $this->assertTrue($released);
    }
}
