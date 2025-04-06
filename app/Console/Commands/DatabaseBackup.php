<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class DatabaseBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup {--upload : Upload backup to cloud storage}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a database backup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting database backup...');
        
        // Get database configuration
        $database = config('database.connections.pgsql.database');
        $username = config('database.connections.pgsql.username');
        $password = config('database.connections.pgsql.password');
        $host = config('database.connections.pgsql.host');
        
        // Create backup filename with date
        $filename = 'backup-' . $database . '-' . Carbon::now()->format('Y-m-d-H-i-s') . '.sql';
        $backupPath = storage_path('app/backups');
        $filePath = $backupPath . '/' . $filename;
        
        // Create backups directory if it doesn't exist
        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0755, true);
        }
        
        // Set up pg_dump command
        $command = [
            'pg_dump',
            '--host=' . $host,
            '--username=' . $username,
            '--format=custom',
            '--file=' . $filePath,
            $database
        ];
        
        // Execute pg_dump command
        $process = new Process($command);
        $process->setTimeout(3600); // 1 hour timeout
        
        // Set password as an environment variable
        $process->setEnv(['PGPASSWORD' => $password]);
        
        try {
            $this->info('Running database dump...');
            $process->mustRun();
            $this->info('Database backup completed: ' . $filePath);
            
            // Calculate file size
            $size = round(filesize($filePath) / 1024 / 1024, 2);
            $this->info("Backup size: {$size} MB");
            
            // Upload to cloud if specified
            if ($this->option('upload')) {
                $this->uploadToCloudStorage($filename, $filePath);
            }
            
            // Cleanup old backups (keep last 7 days)
            $this->cleanupOldBackups();
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            Log::error('Database backup failed', [
                'error' => $e->getMessage(),
                'output' => $process->getErrorOutput()
            ]);
            return 1;
        }
    }
    
    /**
     * Upload backup to cloud storage
     */
    protected function uploadToCloudStorage($filename, $filePath)
    {
        try {
            $this->info('Uploading backup to cloud storage...');
            
            // Store on cloudinary as a raw file
            $cloudinary = app(\Cloudinary\Cloudinary::class);
            $response = $cloudinary->uploadApi()->upload($filePath, [
                'resource_type' => 'raw',
                'public_id' => 'backups/' . $filename,
                'use_filename' => true,
                'overwrite' => true
            ]);
            
            $this->info('Backup uploaded to Cloudinary: ' . $response['secure_url']);
        } catch (\Exception $e) {
            $this->error('Cloud upload failed: ' . $e->getMessage());
            Log::error('Backup cloud upload failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Clean up old backups
     */
    protected function cleanupOldBackups()
    {
        $this->info('Cleaning up old backups...');
        $backupPath = storage_path('app/backups');
        
        // Get all backup files
        $files = glob($backupPath . '/backup-*.sql');
        
        // Sort files by modification time (oldest first)
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Keep only the last 7 backups, delete others
        $keepCount = 7;
        if (count($files) > $keepCount) {
            $filesToDelete = array_slice($files, 0, count($files) - $keepCount);
            
            foreach ($filesToDelete as $file) {
                unlink($file);
                $this->info('Deleted old backup: ' . basename($file));
            }
        }
    }
} 