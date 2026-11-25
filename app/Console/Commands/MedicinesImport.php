<?php

namespace App\Console\Commands;

use App\Models\Central\DrugListEntry;
use Illuminate\Console\Command;

/**
 * Loads database/data/medicines.csv into the platform list. Medicines already in it are left as
 * the panel keeps them (the panel is where they are changed); only new codes are added.
 * --refresh also writes the words of the file over the rows that are there.
 */
class MedicinesImport extends Command
{
    protected $signature = 'medicines:import {--file= : another file, in the same columns} {--refresh : overwrite the rows already there with the file}';

    protected $description = 'Load the list of medicines of the platform into the central database';

    public function handle(): int
    {
        $file = $this->option('file') ?: database_path('data/medicines.csv');
        if (! is_file($file)) {
            $this->error('No file at '.$file);

            return self::FAILURE;
        }
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        $need = ['code', 'name', 'strength', 'form', 'route', 'class', 'mg_per_unit', 'controlled', 'brands'];
        if ($header !== $need) {
            $this->error('The file must have the columns '.implode(', ', $need).'.');

            return self::FAILURE;
        }
        $known = DrugListEntry::pluck('id', 'code')->all();
        $new = [];
        $updated = 0;
        $now = now();
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            $r = array_combine($header, $row);
            $values = ['name' => $r['name'], 'strength' => $r['strength'] ?: null, 'form' => $r['form'], 'route' => $r['route'] ?: null, 'class' => $r['class'] ?: null,
                'mg_per_unit' => $r['mg_per_unit'] !== '' ? (float) $r['mg_per_unit'] : null, 'controlled' => $r['controlled'] === '1', 'brands' => $r['brands'] ?: null];
            if (isset($known[$r['code']])) {
                if ($this->option('refresh')) {
                    DrugListEntry::whereKey($known[$r['code']])->update($values + ['updated_at' => $now]);
                    $updated++;
                }
            } else {
                $new[] = $values + ['code' => $r['code'], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
                $known[$r['code']] = true;
            }
        }
        fclose($handle);
        foreach (array_chunk($new, 200) as $chunk) {
            DrugListEntry::insert($chunk);
        }
        $this->info(count($new).' medicines added'.($updated ? ', '.$updated.' refreshed' : '').'; '.DrugListEntry::count().' in the list.');

        return self::SUCCESS;
    }
}
