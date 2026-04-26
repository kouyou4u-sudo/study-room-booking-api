<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $cancelUrl;

    public function __construct(public Reservation $reservation)
    {
        $this->cancelUrl = url('/api/reservations/cancel/' . $reservation->cancel_token);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【自習室予約】本予約が確定しました'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservations.confirmed',
            with: [
                'reservation' => $this->reservation,
                'cancelUrl' => $this->cancelUrl,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}