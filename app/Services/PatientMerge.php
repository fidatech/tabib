<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two records of one person: everything of the duplicate (visits, history, vitals, documents,
 * prescriptions, invoices, appointments, messages ...) moves to the record that is kept, so both
 * histories are in one chart. The duplicate is not deleted: it stays, closed, pointing at the kept one.
 */
class PatientMerge
{
    /** Details of the person the kept record may lack: they are taken from the duplicate. */
    private const FILL = ['father_name', 'date_of_birth', 'dob_estimated', 'phone', 'phone2', 'tazkira', 'address', 'blood_group', 'emergency_name', 'emergency_phone', 'photo'];

    /** @return array<string, int> rows moved, by table */
    public static function merge(Patient $keep, Patient $duplicate, string $reason, int $userId): array
    {
        if ($keep->id === $duplicate->id) {
            throw new UserError(__('Choose another record: a record cannot be merged into itself.'));
        }
        if ($keep->merged_into_id || $duplicate->merged_into_id) {
            throw new UserError(__('One of these records was merged already.'));
        }

        return DB::transaction(function () use ($keep, $duplicate, $reason, $userId) {
            $moved = [];
            foreach (self::tables() as $table) {
                if ($table === 'patient_social') {
                    $moved[$table] = self::social($keep->id, $duplicate->id);

                    continue;
                }
                $n = DB::table($table)->where('patient_id', $duplicate->id)->update(['patient_id' => $keep->id]);
                if ($n) {
                    $moved[$table] = $n;
                }
            }
            $fill = [];
            foreach (self::FILL as $field) {
                if (blank($keep->{$field}) && filled($duplicate->{$field}) && Schema::hasColumn('patients', $field)) {
                    $fill[$field] = $duplicate->getRawOriginal($field);
                }
            }
            if ($fill) {
                $keep->forceFill($fill)->save();
            }
            $duplicate->forceFill(['merged_into_id' => $keep->id, 'merged_at' => now()])->save();
            $duplicate->delete();
            audit('merged', $keep, __(':duplicate merged into :keep: :reason', ['duplicate' => $duplicate->number.' '.$duplicate->name, 'keep' => $keep->number, 'reason' => $reason]));

            return array_filter($moved);
        });
    }

    /** Every table of the clinic that holds rows of a patient. */
    private static function tables(): array
    {
        $tables = [];
        foreach (array_column(Schema::getTables(), 'name') as $table) {
            if ($table !== 'patients' && Schema::hasColumn($table, 'patient_id')) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    /** One row per patient: the kept row gets what it lacks; when he had none, the duplicate's row moves. */
    private static function social(int $keep, int $duplicate): int
    {
        $from = DB::table('patient_social')->where('patient_id', $duplicate)->first();
        if (! $from) {
            return 0;
        }
        $to = DB::table('patient_social')->where('patient_id', $keep)->first();
        if (! $to) {
            return DB::table('patient_social')->where('id', $from->id)->update(['patient_id' => $keep]);
        }
        $fill = [];
        foreach ((array) $from as $col => $value) {
            if (! in_array($col, ['id', 'patient_id', 'created_at', 'updated_at'], true) && $value !== null && $value !== '' && ($to->{$col} ?? null) === null) {
                $fill[$col] = $value;
            }
        }

        return $fill ? DB::table('patient_social')->where('id', $to->id)->update($fill) : 0;
    }
}
