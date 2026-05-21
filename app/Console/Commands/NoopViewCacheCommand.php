<?php

namespace App\Console\Commands;

class NoopViewCacheCommand extends NoopLaravelCacheCommand
{
    protected $signature   = 'view:cache';
    protected $description = 'No-op stub (Lumen has no view cache)';
}
