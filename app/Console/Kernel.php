<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\SyncDistributorUsers::class,
        \App\Console\Commands\MailTestCommand::class,
        // Stubs for Laravel-only commands that Railpack invokes during build.
        // See NoopLaravelCacheCommand.php for the why.
        \App\Console\Commands\NoopConfigCacheCommand::class,
        \App\Console\Commands\NoopConfigClearCommand::class,
        \App\Console\Commands\NoopRouteCacheCommand::class,
        \App\Console\Commands\NoopViewCacheCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Nightly Sage backfill. Uses --stale so only users never synced or
        // whose last sync is older than 24h get re-fetched, keeping the
        // middleware load proportional to actual changes.
        //
        // Requires a system cron entry on the host:
        //   * * * * * cd /path/to/api.shop.biotech-dental.com && php artisan schedule:run >> /dev/null 2>&1
        $schedule->command('users:sync-sage --stale')
            ->dailyAt('03:15')
            ->withoutOverlapping(60)        // skip if a previous run is still going (60 min mutex)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/sync-sage.log'));
    }
}
