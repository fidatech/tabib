<?php

namespace App\Models;

use App\Models\Concerns\HasRevisions;
use Illuminate\Database\Eloquent\Model;

/** The way of life of a patient: one row for each patient, its changes kept. */
class PatientSocial extends Model
{
    use HasRevisions;

    public const HABITS = ['never' => 'Never', 'former' => 'Former', 'current' => 'Current'];

    public const MARITAL = ['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'divorced' => 'Divorced'];

    protected $table = 'patient_social';

    protected $guarded = [];
}
