<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;

/** The starting words of a visit note for a kind of visit; dental and antenatal ones also bring their form. */
class NoteTemplate extends Model
{
    use Audited;

    public const KINDS = ['general' => 'General', 'dental' => 'Dental (tooth chart)', 'antenatal' => 'Antenatal (pregnancy form)'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** The templates every clinic starts with; it changes them as it likes. */
    public static function seedDefaults(): void
    {
        if (static::query()->exists()) {
            return;
        }
        $rows = [
            ['General visit', 'general', null, "Onset:\nDuration:\nAssociated symptoms:", "General: \nChest: \nAbdomen: ", null, "Medicines:\nAdvice:\nTests:"],
            ['Follow-up', 'general', 'Follow-up of ', 'Since the last visit:', 'Findings:', 'Better / same / worse', 'Continue / change treatment:'],
            ['Child', 'general', null, "Feeding:\nVaccines up to date: yes / no\nDevelopment:", "Weight / height on the growth chart:\nHydration:\nENT:\nChest:", null, "Dose by weight checked.\nAdvice to the mother:"],
            ['Dental', 'dental', null, 'Pain: tooth / since / hot-cold / night', "Extra-oral:\nIntra-oral:\nGums:\nOcclusion:", null, "Treatment done today:\nNext session:"],
            ['Antenatal visit', 'antenatal', 'Antenatal visit', "Complaints: bleeding / pain / headache / leaking / fetal movements\nPrevious pregnancies:", "General condition:\nPallor:\nOedema:", null, "Iron and folic acid:\nTetanus vaccine:\nDanger signs explained:\nNext visit:"],
        ];
        foreach ($rows as [$name, $kind, $complaint, $history, $examination, $assessment, $plan]) {
            static::create(compact('name', 'kind', 'complaint', 'history', 'examination', 'assessment', 'plan') + ['is_active' => true]);
        }
    }
}
