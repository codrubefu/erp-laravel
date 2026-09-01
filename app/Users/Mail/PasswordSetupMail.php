<?php

namespace App\Users\Mail;

use App\Users\Models\User;
use App\Users\Services\OrganizationMailerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordSetupMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $link;

    public function __construct(
        public User $user,
        string $token,
        public bool $isNewAccount = true,
    ) {
        $this->link = rtrim((string) config('app.frontend_url'), '/')
            .'/set-password?token='.$token
            .'&email='.urlencode($user->email);
    }

    public function build(): self
    {
        $mail = $this
            ->subject($this->isNewAccount ? 'Bine ai venit! Setează-ți parola' : 'Resetare parolă')
            ->view('emails.users.password-setup');

        if ($this->user->organization) {
            app(OrganizationMailerService::class)->apply($mail, $this->user->organization);
        }

        return $mail;
    }
}
