<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A question or a problem a clinic sent to the support of Fida Technologies, with its answers. */
class Ticket extends Model
{
    use UsesCentralConnection;

    public const STATUSES = ['open' => 'Waiting for support', 'answered' => 'Answered', 'closed' => 'Closed'];

    public const PRIORITIES = ['normal' => 'Normal', 'urgent' => 'Urgent'];

    protected $guarded = [];

    protected $casts = ['last_message_at' => 'datetime'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }

    /** One more message in the conversation; the side that wrote it decides who is waiting now. */
    public function say(string $side, string $author, string $body): TicketMessage
    {
        $message = $this->messages()->create(['side' => $side, 'author' => $author, 'body' => $body]);
        $this->update(['status' => $side === 'support' ? 'answered' : 'open', 'last_message_at' => now()]);

        return $message;
    }
}
