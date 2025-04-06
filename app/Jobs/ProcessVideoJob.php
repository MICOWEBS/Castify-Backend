<?php

namespace App\Jobs;

use App\Models\Video;
use App\Models\VideoMetric;
use App\Notifications\VideoProcessingFailed;
use App\Services\MediaProcessingService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;

class ProcessVideoJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels, Dispatchable;

    /**
     * The video instance.
     *
     * @var \App\Models\Video
     */
    protected $video;
    
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;
    
    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600; // 1 hour
    
    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [60, 300, 600]; // 1 minute, 5 minutes, 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video)
    {
        $this->video = $video;
        $this->onQueue('video-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(MediaProcessingService $mediaService): void
    {
        try {
            // Update video status to processing
            $this->video->status = 'processing';
            $this->video->save();

            Log::info('Processing video', [
                'video_id' => $this->video->id,
                'title' => $this->video->title,
                'attempt' => $this->attempts()
            ]);

            // Use the advanced MediaProcessingService
            $result = $mediaService->processVideo($this->video);
            
            if (!$result) {
                throw new Exception('Video processing failed without specific error');
            }

            // Update video status to complete
            $this->video->status = 'complete';
            $this->video->save();

            // Ensure video metric exists
            if (!$this->video->metrics) {
                VideoMetric::create([
                    'video_id' => $this->video->id,
                ]);
            }

            Log::info('Video processed successfully', [
                'video_id' => $this->video->id,
                'title' => $this->video->title,
                'processing_time' => $this->getJobRunningTimeInSeconds()
            ]);
        } catch (Exception $e) {
            Log::error('Error processing video', [
                'video_id' => $this->video->id,
                'title' => $this->video->title,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'processing_time' => $this->getJobRunningTimeInSeconds()
            ]);
            
            // Mark video as failed if this is the final retry
            if ($this->attempts() >= $this->tries) {
                $this->video->status = 'failed';
                $this->video->processing_error = $e->getMessage();
                $this->video->save();
                
                // Send notification to video owner and admins
                $this->sendFailureNotifications($e);
            } else {
                // If we have retries left, mark as pending for retry
                $this->video->status = 'pending';
                $this->video->save();
            }
            
            // Release the job with backoff
            $backoffTime = $this->backoff[$this->attempts() - 1] ?? 600;
            $this->release($backoffTime);
        }
    }
    
    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        // Update video status to failed
        $this->video->status = 'failed';
        $this->video->processing_error = $exception->getMessage();
        $this->video->save();
        
        // Send notifications about the failure
        $this->sendFailureNotifications($exception);
        
        Log::error('Video processing job failed completely', [
            'video_id' => $this->video->id,
            'title' => $this->video->title,
            'error' => $exception->getMessage(),
            'total_attempts' => $this->attempts()
        ]);
    }
    
    /**
     * Send notifications about video processing failure.
     */
    protected function sendFailureNotifications(Exception $exception): void
    {
        // Notify the video owner
        $this->video->user->notify(new VideoProcessingFailed($this->video, $exception->getMessage()));
        
        // Notify all admin users
        $adminEmails = config('services.notifications.admin_emails', []);
        Notification::route('mail', $adminEmails)
            ->notify(new VideoProcessingFailed($this->video, $exception->getMessage(), true));
    }
    
    /**
     * Calculate how long the job has been running.
     */
    protected function getJobRunningTimeInSeconds(): int
    {
        if (!$this->job) {
            return 0;
        }
        
        return time() - strtotime($this->job->getReservedAt());
    }
}
