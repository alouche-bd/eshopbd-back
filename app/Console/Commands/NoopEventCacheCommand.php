<?php

namespace App\Console\Commands;

class NoopEventCacheCommand extends NoopLaravelCacheCommand
{
    protected $signature   = 'event:cache';
    protected $description = 'No-op stub (Lumen has no event cache)';
}
