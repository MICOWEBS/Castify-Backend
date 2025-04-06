<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcessingAnalyticsController extends Controller
{
    /**
     * Dashboard for video processing analytics
     */
    public function dashboard(Request $request)
    {
        // Date filtering
        $startDate = $request->get('start_date') 
            ? Carbon::parse($request->get('start_date')) 
            : Carbon::now()->subDays(30);
            
        $endDate = $request->get('end_date') 
            ? Carbon::parse($request->get('end_date')) 
            : Carbon::now();
            
        // Get video processing statistics
        $processingStats = $this->getProcessingStats($startDate, $endDate);
        $dailyStats = $this->getDailyStats($startDate, $endDate);
        $errorStats = $this->getErrorStats($startDate, $endDate);
        $formatStats = $this->getFormatStats($startDate, $endDate);
        $subtitleStats = $this->getSubtitleStats($startDate, $endDate);
        
        return view('admin.analytics.processing', compact(
            'processingStats',
            'dailyStats',
            'errorStats',
            'formatStats',
            'subtitleStats',
            'startDate',
            'endDate'
        ));
    }
    
    /**
     * API endpoint for video processing analytics
     */
    public function apiStats(Request $request): JsonResponse
    {
        // Date filtering
        $startDate = $request->get('start_date') 
            ? Carbon::parse($request->get('start_date')) 
            : Carbon::now()->subDays(30);
            
        $endDate = $request->get('end_date') 
            ? Carbon::parse($request->get('end_date')) 
            : Carbon::now();
            
        // Get statistics
        $processingStats = $this->getProcessingStats($startDate, $endDate);
        $dailyStats = $this->getDailyStats($startDate, $endDate);
        $errorStats = $this->getErrorStats($startDate, $endDate);
        $formatStats = $this->getFormatStats($startDate, $endDate);
        $subtitleStats = $this->getSubtitleStats($startDate, $endDate);
        
        return response()->json([
            'processing' => $processingStats,
            'daily' => $dailyStats,
            'errors' => $errorStats,
            'formats' => $formatStats,
            'subtitles' => $subtitleStats,
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ]
        ]);
    }
    
    /**
     * Get basic video processing statistics
     */
    protected function getProcessingStats(Carbon $startDate, Carbon $endDate): array
    {
        // Get video counts by status
        $statusCounts = Video::select('status', DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
            
        // Get average processing time
        $avgProcessingTime = Video::whereNotNull('processing_duration')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->avg('processing_duration');
            
        // Get average retry count
        $avgRetries = Video::where('processing_attempts', '>', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->avg('processing_attempts');
            
        // Get total processed videos
        $totalProcessed = Video::where('status', 'complete')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
            
        // Get failed video count
        $totalFailed = Video::where('status', 'failed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
            
        // Get success rate
        $successRate = $totalProcessed + $totalFailed > 0
            ? ($totalProcessed / ($totalProcessed + $totalFailed)) * 100
            : 0;
            
        return [
            'status_counts' => $statusCounts,
            'avg_processing_time' => $avgProcessingTime ? round($avgProcessingTime, 2) : 0,
            'avg_retries' => $avgRetries ? round($avgRetries, 2) : 0,
            'total_processed' => $totalProcessed,
            'total_failed' => $totalFailed,
            'success_rate' => round($successRate, 2),
        ];
    }
    
    /**
     * Get daily processing statistics
     */
    protected function getDailyStats(Carbon $startDate, Carbon $endDate): array
    {
        $dailyStats = Video::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "complete" THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed'),
                DB::raw('AVG(processing_duration) as avg_duration')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->get();
            
        return $dailyStats->toArray();
    }
    
    /**
     * Get error statistics
     */
    protected function getErrorStats(Carbon $startDate, Carbon $endDate): array
    {
        // Get common error messages
        $errorTypes = Video::select(
                'processing_error',
                DB::raw('COUNT(*) as count')
            )
            ->where('status', 'failed')
            ->whereNotNull('processing_error')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('processing_error')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
            
        return [
            'types' => $errorTypes,
        ];
    }
    
    /**
     * Get statistics about video formats
     */
    protected function getFormatStats(Carbon $startDate, Carbon $endDate): array
    {
        // Get counts of adaptive streaming enabled videos
        $adaptiveCount = Video::where('adaptive_streaming', true)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
            
        $nonAdaptiveCount = Video::where('adaptive_streaming', false)
            ->where('status', 'complete')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
            
        // Get counts of DRM protected videos
        $drmProtectedCount = Video::where('is_protected', true)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
            
        // Get DRM types if available
        $drmTypes = [];
        if (Video::whereNotNull('drm_type')->exists()) {
            $drmTypes = Video::select('drm_type', DB::raw('COUNT(*) as count'))
                ->whereNotNull('drm_type')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('drm_type')
                ->pluck('count', 'drm_type')
                ->toArray();
        }
        
        return [
            'adaptive' => [
                'enabled' => $adaptiveCount,
                'disabled' => $nonAdaptiveCount,
            ],
            'drm' => [
                'protected' => $drmProtectedCount,
                'types' => $drmTypes,
            ],
        ];
    }
    
    /**
     * Get statistics about subtitle generation
     */
    protected function getSubtitleStats(Carbon $startDate, Carbon $endDate): array
    {
        // Count videos with subtitles
        $withSubtitles = Video::where('has_subtitles', true)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
            
        $withoutSubtitles = Video::where('has_subtitles', false)
            ->where('status', 'complete')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
            
        // Get language distribution if available
        $languages = [];
        $videoIds = Video::where('has_subtitles', true)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->pluck('id');
            
        if (count($videoIds) > 0) {
            // This is a simplified approach - in a real app, you might have a subtitles table
            $videos = Video::whereIn('id', $videoIds)
                ->whereNotNull('subtitle_languages')
                ->get();
                
            $langCounts = [];
            foreach ($videos as $video) {
                $langs = json_decode($video->subtitle_languages, true) ?? [];
                foreach ($langs as $lang) {
                    if (!isset($langCounts[$lang])) {
                        $langCounts[$lang] = 0;
                    }
                    $langCounts[$lang]++;
                }
            }
            
            $languages = $langCounts;
        }
        
        return [
            'with_subtitles' => $withSubtitles,
            'without_subtitles' => $withoutSubtitles,
            'languages' => $languages,
        ];
    }
}
