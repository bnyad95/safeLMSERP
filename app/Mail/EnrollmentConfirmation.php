<?php

namespace App\Mail;

use App\Models\Course;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnrollmentConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public Course $course,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Enrollment Confirmation - '.$this->course->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.enrollment-confirmation',
        );
    }
}
