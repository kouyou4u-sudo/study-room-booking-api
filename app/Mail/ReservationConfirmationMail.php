<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $confirmationUrl;

    public function __construct(public Reservation $reservation)
    {
        $this->confirmationUrl = url('/api/reservations/confirm/' . $reservation->confirmation_token);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【自習室予約】仮予約確認のお願い'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservations.confirmation',
            with: [
                'reservation' => $this->reservation,
                'confirmationUrl' => $this->confirmationUrl,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}