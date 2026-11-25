<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A request to a laboratory or for imaging; its result is written or uploaded when it comes back. */
class Order extends Model
{
    use Audited;

    public const KINDS = ['lab' => 'Laboratory', 'imaging' => 'Imaging'];

    public const STATUSES = ['ordered' => ['Waiting for the result', 'bg-warning text-dark'], 'resulted' => ['Result in', 'bg-success'], 'cancelled' => ['Cancelled', 'bg-secondary']];

    /** The usual tests, in groups, so a request is a few clicks; anything else can be written. */
    public const COMMON = [
        'lab' => [
            'Blood' => ['CBC (complete blood count)', 'ESR', 'Hemoglobin', 'Blood group and Rh', 'Peripheral smear', 'Malaria (MP / RDT)'],
            'Sugar' => ['Fasting blood sugar', 'Random blood sugar', 'HbA1c', 'OGTT'],
            'Chemistry' => ['Lipid profile', 'Liver function tests (LFT)', 'Renal function tests (urea, creatinine)', 'Uric acid', 'Electrolytes (Na, K, Cl)', 'Calcium', 'CRP'],
            'Hormones' => ['TSH', 'Free T4', 'Beta-hCG (pregnancy)', 'Vitamin D', 'Vitamin B12', 'Ferritin'],
            'Infections' => ['Widal (typhoid)', 'Typhidot', 'HBsAg', 'Anti-HCV', 'HIV', 'H. pylori', 'Brucella', 'VDRL'],
            'Urine and stool' => ['Urine routine (RE)', 'Urine culture', 'Urine pregnancy test', 'Stool routine (RE)', 'Stool occult blood'],
        ],
        'imaging' => [
            'X-ray' => ['Chest X-ray (PA)', 'X-ray of a joint or bone', 'X-ray of the spine', 'X-ray of the abdomen'],
            'Ultrasound' => ['Ultrasound abdomen', 'Ultrasound pelvis', 'Obstetric ultrasound', 'Ultrasound thyroid', 'Ultrasound breast', 'Doppler'],
            'Heart' => ['ECG', 'Echocardiography'],
            'Other' => ['CT scan', 'MRI', 'Mammography', 'Endoscopy'],
        ],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tests' => 'array', 'resulted_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PatientDocument::class, 'result_document_id');
    }

    public function resulter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resulted_by');
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status][0] ?? $this->status);
    }

    public function statusClass(): string
    {
        return self::STATUSES[$this->status][1] ?? 'bg-secondary';
    }

    public function summary(): string
    {
        return implode(', ', (array) $this->tests);
    }

    public function auditLabel(): string
    {
        return $this->number;
    }
}
