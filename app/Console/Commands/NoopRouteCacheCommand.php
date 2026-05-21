<?php

namespace App\Console\Commands;

class NoopRouteCacheCommand extends NoopLaravelCacheCommand
{
    protected $signature   = 'route:cache';
    protected $description = 'No-op stub (Lumen has no route cache)';
}
