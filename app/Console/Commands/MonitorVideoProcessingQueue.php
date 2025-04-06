<?php

namespace App\Console\Commands;

use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class MonitorVideoProcessingQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:monitor-video-processing 
                            {--clear-failed : Clear failed jobs} 
                            {--retry-failed : Retry failed jobs} 
                            {--stuck-threshold=180 : Minutes after which a processing video is considered stuck}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor and manage the video processing queue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Monitoring video processing queue...');
        
        // Get queue statistics
        $this->displayQueueStats();
        
        // Check for stuck videos
        $this->checkForStuckVideos();
        
        // Handle failed jobs
        if ($this->option('clear-failed')) {
            $this->clearFailedJobs();
        }
        
        if ($this->option('retry-failed')) {
            $this->retryFailedJobs();
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Display statistics about the video processing queue
     */
    protected function displayQueueStats(): void
    {
        // Count videos by status
        $videoStats = Video::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
            
        $this->info('Video status counts:');
        $this->table(
            ['Status', 'Count'],
            collect($videoStats)->map(fn ($count, $status) => [$status, $count])->toArray()
        );
        
        // Get failed job count
        $failedCount = DB::table('failed_jobs')
            ->where('queue', 'video-processing')
            ->count();
            
        $this->info("Failed video processing jobs: {$failedCount}");
        
        // Get processing metrics
        $avgProcessingTime = Video::whereNotNull('processing_duration')
            ->avg('processing_duration');
            
        if ($avgProcessingTime) {
            $this->info(sprintf("Average processing time: %.2f seconds (%.2f minutes)", 
                $avgProcessingTime, 
                $avgProcessingTime / 60
            ));
        }
    }
    
    /**
     * Check for videos that are stuck in processing
     */
    protected function checkForStuckVideos(): void
    {
        $stuckThreshold = (int) $this->option('stuck-threshold');
        $stuckTime = Carbon::now()->subMinutes($stuckThreshold);
        
        $stuckVideos = Video::where('status', 'processing')
            ->where('updated_at', '<', $stuckTime)
            ->get();
            
        if ($stuckVideos->isEmpty()) {
            $this->info('No stuck videos found.');
            return;
        }
        
        $this->warn("{$stuckVideos->count()} videos appear to be stuck in processing:");
        
        $tableData = $stuckVideos->map(function ($video) {
            return [
                $video->id,
                $video->title,
                $video->updated_at->diffForHumans(),
                $video->processing_attempts
            ];
        });
        
        $this->table(['ID', 'Title', 'Last Updated', 'Attempts'], $tableData);
        
        if ($this->confirm('Would you like to reset these videos to "pending" status?')) {
            foreach ($stuckVideos as $video) {
                $video->status = 'pending';
                $video->save();
                
                Log::warning("Reset stuck video from processing to pending", [
                    'video_id' => $video->id,
                    'title' => $video->title,
                    'stuck_time' => $video->updated_at->diffForHumans()
                ]);
            }
            
            $this->info('Stuck videos have been reset to pending status.');
        }
    }
    
    /**
     * Clear failed jobs from the failed_jobs table
     */
    protected function clearFailedJobs(): void
    {
        $count = DB::table('failed_jobs')
            ->where('queue', 'video-processing')
            ->delete();
            
        $this->info("Cleared {$count} failed video processing jobs.");
    }
    
    /**
     * Retry failed video processing jobs
     */
    protected function retryFailedJobs(): void
    {
        $failedJobs = DB::table('failed_jobs')
            ->where('queue', 'video-processing')
            ->get();
            
        if ($failedJobs->isEmpty()) {
            $this->info('No failed video processing jobs to retry.');
            return;
        }
        
        $this->info("Retrying {$failedJobs->count()} failed video processing jobs...");
        
        foreach ($failedJobs as $job) {
            // Extract video ID from the failed job payload
            $payload = json_decode($job->payload, true);
            $command = unserialize($payload['data']['command']);
            
            if (isset($command->video)) {
                $videoId = $command->video->id;
                $video = Video::find($videoId);
                
                if ($video) {
                    $this->info("Resetting video {$videoId} to pending status...");
                    $video->status = 'pending';
                    $video->save();
                }
            }
            
            $this->info("Retrying job {$job->id}...");
            Queue::retry($job->id);
        }
        
        $this->info('All failed jobs have been queued for retry.');
    }
}
