<?php

namespace App\Console\Commands;

class NoopOptimizeCommand extends NoopLaravelCacheCommand
{
    protected $signature   = 'optimize';
    protected $description = 'No-op stub (Lumen has no Laravel-style optimize)';
}
