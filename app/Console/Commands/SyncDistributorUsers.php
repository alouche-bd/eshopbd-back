<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Distributor\ClientSyncService;
use Exception;
use Illuminate\Console\Command;

/**
 * Backfill the distributor / Sage columns on existing users.
 *
 *   php artisan users:sync-sage             # all users with a Sage code
 *   php artisan users:sync-sage --stale     # only users never synced or older than 24h
 *   php artisan users:sync-sage --email=x   # one specific user
 *   php artisan users:sync-sage --dry-run   # report what would change, no save
 */
class SyncDistributorUsers extends Command
{
    protected $signature = 'users:sync-sage
        {--stale : only sync users never synced or whose last sync is older than 24h}
        {--email= : sync a single user by email}
        {--dry-run : show what would be synced without saving}
        {--chunk=50 : process N users per chunk}';

    protected $description = 'Backfill billing country, currency, representative, addresses and user_type from Sage for existing users';

    public function handle(ClientSyncService $sync): int
    {
        $query = User::query()->whereNotNull('codeclientGC');

        if ($email = $this->option('email')) {
            $query->where('email', $email);
        } elseif ($this->option('stale')) {
            $query->where(function ($q) {
                $q->whereNull('sage_synced_at')
                  ->orWhere('sage_synced_at', '<', now()->subDay());
            });
        }

        $total   = (int) $query->count();
        $chunk   = (int) $this->option('chunk');
        $dryRun  = (bool) $this->option('dry-run');
        $synced  = 0;
        $failed  = 0;
        $skipped = 0;

        if ($total === 0) {
            $this->info('No users matched the criteria.');
            return 0;
        }

        $this->info("Syncing {$total} user(s) from Sage" . ($dryRun ? ' (dry-run)' : '') . '...');
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunkById($chunk, function ($users) use ($sync, $dryRun, $bar, &$synced, &$failed, &$skipped) {
            foreach ($users as $user) {
                try {
                    if ($dryRun) {
                        $skipped++;
                    } else {
                        $before = $user->only(['user_type', 'billing_country_code', 'sage_client_code', 'currency']);
                        $sync->syncFromSage($user);
                        $after = $user->fresh()->only(['user_type', 'billing_country_code', 'sage_client_code', 'currency']);
                        if ($before !== $after) {
                            $synced++;
                        } else {
                            $skipped++;
                        }
                    }
                } catch (Exception $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("Failed for user #{$user->id} ({$user->email}): " . $e->getMessage());
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Synced:  {$synced}");
        $this->info("Skipped: {$skipped}");
        if ($failed > 0) {
            $this->warn("Failed:  {$failed}");
        }

        return $failed > 0 ? 1 : 0;
    }
}
