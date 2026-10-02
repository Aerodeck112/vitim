<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Emailul de invitare: linkul pentru prima parolă, valabil 7 zile. */
final class Invitation extends Notification
{
    public function __construct(public readonly string $token, private readonly ?string $organization) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = (int) round(config('auth.passwords.invites.expire') / 1440);

        return (new MailMessage)
            ->subject($this->organization ? "Contul tău VITIM pentru {$this->organization}" : 'Contul tău VITIM')
            ->greeting('Bună, '.$notifiable->name.'!')
            ->line($this->organization
                ? "Echipa VITIM ți-a creat acces la panoul firmei {$this->organization}: lucrările făcute pe site, rapoartele lunare, cererile clienților și conversațiile."
                : 'Echipa VITIM ți-a creat un cont în panoul VITIM.')
            ->line('Ca să intri, setează-ți parola:')
            ->action('Creează parola', route('password.reset', ['token' => $this->token, 'email' => $notifiable->email, 'invitatie' => 1]))
            ->line('Linkul e valabil '.($days >= 1 ? $days.' zile' : config('auth.passwords.invites.expire').' de minute').'. Dacă expiră, cere echipei VITIM să ți-l retrimită sau folosește „Am uitat parola” pe pagina de autentificare.')
            ->salutation('Echipa VITIM');
    }
}
