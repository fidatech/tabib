<?php

namespace App\Console\Commands;

use App\Models\Central\Icd10Code;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Loads the ICD-10-CM codes of database/data/icd10cm.csv.gz into the central database. Run again after a new release: it replaces them. */
class Icd10Import extends Command
{
    protected $signature = 'icd10:import {--file= : another file, in the same columns}';

    protected $description = 'Load the ICD-10 diagnosis codes into the central database';

    public function handle(): int
    {
        $file = $this->option('file') ?: database_path('data/icd10cm.csv.gz');
        if (! is_file($file)) {
            $this->error('No file at '.$file);

            return self::FAILURE;
        }
        $handle = gzopen($file, 'r');
        $header = fgetcsv($handle);
        if ($header !== ['code', 'description', 'billable']) {
            $this->error('The file must have the columns code, description, billable.');

            return self::FAILURE;
        }

        // the names written in Dari or Pashto stay
        $local = Icd10Code::whereNotNull('description_local')->pluck('description_local', 'code')->all();
        $count = 0;
        $connection = DB::connection((new Icd10Code)->getConnectionName());
        $connection->transaction(function () use ($handle, $local, &$count, $connection) {
            $connection->table('icd10_codes')->delete();
            $batch = [];
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 3) {
                    continue;
                }
                $batch[] = ['code' => $row[0], 'description' => mb_substr($row[1], 0, 255), 'description_local' => $local[$row[0]] ?? null, 'billable' => $row[2] === '1'];
                if (count($batch) === 500) {
                    $connection->table('icd10_codes')->insert($batch);
                    $count += 500;
                    $batch = [];
                }
            }
            if ($batch) {
                $connection->table('icd10_codes')->insert($batch);
                $count += count($batch);
            }
        });
        gzclose($handle);
        $this->info($count.' diagnosis codes loaded.');

        return self::SUCCESS;
    }
}
