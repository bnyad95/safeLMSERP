<?php

namespace App\Mail;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PerformanceWarning extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public string $reason,
        public array $details = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Academic Performance Warning',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.performance-warning',
        );
    }
}
