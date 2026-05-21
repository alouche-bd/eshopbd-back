<?php

namespace App\Console\Commands;

class NoopConfigCacheCommand extends NoopLaravelCacheCommand
{
    protected $signature   = 'config:cache';
    protected $description = 'No-op stub (Lumen does not cache config)';
}
