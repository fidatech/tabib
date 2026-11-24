<?php

namespace App\Mail;

use App\Models\Central\Signup;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the administrator: a clinic registered on the website; approve or reject it from the email. */
class SignupReceived extends Mailable
{
    public function __construct(public Signup $signup, public string $approveUrl, public string $rejectUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New registration: '.$this->signup->name.' ('.$this->signup->domain().')');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.signup-received');
    }
}
