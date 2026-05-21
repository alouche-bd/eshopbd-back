<?php

namespace App\Console\Commands;

class NoopStorageLinkCommand extends NoopLaravelCacheCommand
{
    protected $signature   = 'storage:link';
    protected $description = 'No-op stub (Lumen has no storage:link — public assets served directly)';
}
