<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Base for no-op stubs of Laravel's config:cache / route:cache / view:cache
 * commands. Lumen doesn't ship these (Illuminate\Foundation isn't pulled in)
 * but Railpack's PHP+Laravel build provider invokes them unconditionally
 * during the build phase, causing exit code 1 and a failed deploy.
 *
 * Concrete subclasses live in their own files (PSR-4 requires one class
 * per file). They intentionally do nothing — Lumen has no config/route/view
 * cache to populate.
 */
abstract class NoopLaravelCacheCommand extends Command
{
    public function handle(): int
    {
        $this->info("Lumen: '{$this->signature}' no-op (Laravel-only command, skipped)");
        return 0;
    }
}
