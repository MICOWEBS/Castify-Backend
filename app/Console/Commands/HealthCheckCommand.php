<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Exception;

class HealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the health of various system components';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting system health check...');
        $errors = [];

        // Check database connection
        try {
            $this->info('Checking database connection...');
            DB::connection()->getPdo();
            $this->info('✓ Database connection successful');
        } catch (Exception $e) {
            $this->error('✗ Database connection failed: ' . $e->getMessage());
            $errors[] = 'Database: ' . $e->getMessage();
        }

        // Check Redis connection
        try {
            $this->info('Checking Redis connection...');
            Redis::connection()->ping();
            $this->info('✓ Redis connection successful');
        } catch (Exception $e) {
            $this->error('✗ Redis connection failed: ' . $e->getMessage());
            $errors[] = 'Redis: ' . $e->getMessage();
        }

        // Check Cloudinary connection
        try {
            $this->info('Checking Cloudinary connection...');
            $cloudinary = app(\Cloudinary\Cloudinary::class);
            $result = $cloudinary->adminApi()->ping();
            $this->info('✓ Cloudinary connection successful');
        } catch (Exception $e) {
            $this->error('✗ Cloudinary connection failed: ' . $e->getMessage());
            $errors[] = 'Cloudinary: ' . $e->getMessage();
        }

        // Check email service
        try {
            $this->info('Checking email service...');
            $mailConfig = config('mail');
            if (empty($mailConfig['mailers'][$mailConfig['default']])) {
                throw new Exception('Email configuration is missing');
            }
            $this->info('✓ Email configuration is valid');
        } catch (Exception $e) {
            $this->error('✗ Email service check failed: ' . $e->getMessage());
            $errors[] = 'Email: ' . $e->getMessage();
        }

        // Check queue system
        try {
            $this->info('Checking queue system...');
            $queueConfig = config('queue');
            if (empty($queueConfig['connections'][$queueConfig['default']])) {
                throw new Exception('Queue configuration is missing');
            }
            $this->info('✓ Queue configuration is valid');
        } catch (Exception $e) {
            $this->error('✗ Queue system check failed: ' . $e->getMessage());
            $errors[] = 'Queue: ' . $e->getMessage();
        }

        // Check disk space
        try {
            $this->info('Checking disk space...');
            $freeSpace = disk_free_space(storage_path());
            $totalSpace = disk_total_space(storage_path());
            $usedPercentage = 100 - ($freeSpace / $totalSpace * 100);
            
            if ($usedPercentage > 90) {
                throw new Exception("Disk usage is at {$usedPercentage}%, which is over 90%");
            }
            
            $this->info("✓ Disk space OK ({$usedPercentage}% used)");
        } catch (Exception $e) {
            $this->error('✗ Disk space check failed: ' . $e->getMessage());
            $errors[] = 'Disk: ' . $e->getMessage();
        }

        // Check external services with HTTP
        try {
            $this->info('Checking external services...');
            $response = Http::timeout(5)->get(config('services.status_endpoint', 'https://www.google.com'));
            if (!$response->successful()) {
                throw new Exception('External service returned status ' . $response->status());
            }
            $this->info('✓ External services are reachable');
        } catch (Exception $e) {
            $this->error('✗ External services check failed: ' . $e->getMessage());
            $errors[] = 'External: ' . $e->getMessage();
        }

        // Summary
        $this->newLine();
        if (empty($errors)) {
            $this->info('All systems are operational! ✓');
            return 0;
        } else {
            $this->error('Health check completed with ' . count($errors) . ' error(s)');
            foreach ($errors as $error) {
                $this->line('  - ' . $error);
            }
            return 1;
        }
    }
} 