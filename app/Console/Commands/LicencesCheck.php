<?php

namespace App\Console\Commands;

use App\Services\Licence;
use Illuminate\Console\Command;

class LicencesCheck extends Command
{
    protected $signature = 'licences:check';

    protected $description = 'Lock the clinics whose licence and days of grace have ended, open those that were paid';

    public function handle(): int
    {
        $result = Licence::check();
        $this->info($result['locked'].' locked, '.$result['opened'].' opened.');

        return self::SUCCESS;
    }
}
