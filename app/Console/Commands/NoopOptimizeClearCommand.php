<?php

namespace App\Console\Commands;

class NoopOptimizeClearCommand extends NoopLaravelCacheCommand
{
    protected $signature   = 'optimize:clear';
    protected $description = 'No-op stub (no caches to clear in Lumen)';
}
