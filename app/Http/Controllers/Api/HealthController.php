<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Health",
 *     description="API Endpoint for application health check"
 * )
 */
class HealthController extends Controller
{
    /**
     * Check the health of the application
     * 
     * @OA\Get(
     *     path="/api/health",
     *     summary="Check application health",
     *     tags={"Health"},
     *     @OA\Response(
     *         response=200,
     *         description="Application is healthy",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="services", type="object",
     *                 @OA\Property(property="database", type="string", example="ok"),
     *                 @OA\Property(property="storage", type="string", example="ok"),
     *                 @OA\Property(property="ffmpeg", type="string", example="ok")
     *             ),
     *             @OA\Property(property="environment", type="string", example="production"),
     *             @OA\Property(property="version", type="string", example="1.0.0")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Application is unhealthy",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="services", type="object",
     *                 @OA\Property(property="database", type="string", example="error"),
     *                 @OA\Property(property="storage", type="string", example="ok"),
     *                 @OA\Property(property="ffmpeg", type="string", example="ok")
     *             ),
     *             @OA\Property(property="environment", type="string", example="production"),
     *             @OA\Property(property="version", type="string", example="1.0.0")
     *         )
     *     )
     * )
     */
    public function __invoke(): JsonResponse
    {
        $status = "ok";
        $services = [
            'database' => 'ok',
            'storage' => 'ok',
            'ffmpeg' => 'ok',
        ];
        $statusCode = 200;
        
        // Check database connection
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $services['database'] = 'error';
            $status = 'error';
            $statusCode = 500;
            Log::error('Health check failed: Database error', [
                'error' => $e->getMessage()
            ]);
        }
        
        // Check storage
        try {
            $testFile = storage_path('app/health-check-' . time() . '.txt');
            file_put_contents($testFile, 'Health check');
            if (!file_exists($testFile)) {
                throw new \Exception('Could not write to storage');
            }
            unlink($testFile);
        } catch (\Exception $e) {
            $services['storage'] = 'error';
            $status = 'error';
            $statusCode = 500;
            Log::error('Health check failed: Storage error', [
                'error' => $e->getMessage()
            ]);
        }
        
        // Check FFmpeg
        try {
            $ffmpegPath = config('services.ffmpeg.binary_path', '/usr/bin/ffmpeg');
            $checkCommand = escapeshellcmd($ffmpegPath) . ' -version';
            exec($checkCommand, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new \Exception('FFmpeg not available: Return code ' . $returnCode);
            }
        } catch (\Exception $e) {
            $services['ffmpeg'] = 'error';
            $status = 'error';
            $statusCode = 500;
            Log::error('Health check failed: FFmpeg error', [
                'error' => $e->getMessage()
            ]);
        }
        
        return response()->json([
            'status' => $status,
            'services' => $services,
            'environment' => config('app.env'),
            'version' => config('app.version', '1.0.0'),
        ], $statusCode);
    }
} 