<?php

namespace App\Console\Commands;

class NoopCacheClearCommand extends NoopLaravelCacheCommand
{
    protected $signature   = 'cache:clear';
    protected $description = 'No-op stub';
}
