<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run database cleanup
        $schedule->command('videos:cleanup')->daily();
        
        // Refresh recommendations
        $schedule->command('recommendations:generate')->dailyAt('03:00');
        
        // Send summary reports to admins
        $schedule->command('reports:daily')->dailyAt('08:00');
        
        // Clean temporary files
        $schedule->command('storage:cleanup')->weekly();
        
        // Generate sitemap
        $schedule->command('sitemap:generate')->daily()->at('02:30');
        
        // Database maintenance
        $schedule->command('db:vacuum')->weekly()->sundays()->at('01:00');
        
        // Database backup
        $schedule->command('db:backup --upload')->daily()->at('01:00');
        
        // Send weekly engagement emails
        $schedule->command('emails:weekly-digest')->weekly()->mondays()->at('10:00');
        
        // Update video metrics
        $schedule->command('videos:update-metrics')->hourly();
        
        // Run health checks
        $schedule->command('health:check')
            ->everyFiveMinutes()
            ->emailOutputOnFailure(env('ADMIN_NOTIFICATION_EMAILS'));
        
        // Monitor video processing queue every 15 minutes
        $schedule->command('queue:monitor-video-processing --stuck-threshold=60')
                 ->everyFifteenMinutes()
                 ->appendOutputTo(storage_path('logs/queue-monitor.log'));
                 
        // Every day at midnight, run a thorough check and retry failed jobs
        $schedule->command('queue:monitor-video-processing --retry-failed --stuck-threshold=120')
                 ->dailyAt('00:00')
                 ->appendOutputTo(storage_path('logs/queue-monitor.log'));
                 
        // Prune failed jobs weekly to keep the table manageable
        $schedule->command('queue:prune-failed --hours=168') // 7 days
                 ->weekly()
                 ->appendOutputTo(storage_path('logs/queue-monitor.log'));
                 
        // Regular queue maintenance
        $schedule->command('queue:work --stop-when-empty --queue=video-processing')
                 ->everyMinute()
                 ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
} 