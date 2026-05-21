<?php

namespace App\Console\Commands;

class NoopConfigClearCommand extends NoopLaravelCacheCommand
{
    protected $signature   = 'config:clear';
    protected $description = 'No-op stub (nothing to clear in Lumen)';
}
