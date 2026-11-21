<?php

namespace App\Console\Commands;

use App\Services\Amc\AmcFileService;
use Illuminate\Console\Command;

// AMC (21/11/2026): deletes deployment images older than a year (file and row). Scheduled daily by AmcServiceProvider.
class AmcPurgeOldImages extends Command
{
    protected $signature = 'amc:purge-old-images';

    protected $description = 'Delete AMC deployment images older than 1 year';

    public function handle(AmcFileService $files): int
    {
        $count = $files->purgeOldDeployment();
        $this->info($count.' deployment image(s) older than 1 year deleted.');

        return self::SUCCESS;
    }
}
