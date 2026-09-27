<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jawaban satu kolom tambahan untuk satu pendaftar.
 */
#[Fillable(['event_registration_id', 'event_field_id', 'nilai'])]
class EventRegistrationAnswer extends Model
{
    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    public function kolom(): BelongsTo
    {
        return $this->belongsTo(EventField::class, 'event_field_id');
    }
}
