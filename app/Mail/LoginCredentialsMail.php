<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Нэвтрэх мэдээллийг бүртгэлтэй и-мэйл рүү илгээнэ.
 *
 * Хуучин нууц үгийг сэргээх боломжгүй (шифрлэгдэн хадгалагддаг) тул
 * шинэ түр нууц үг үүсгэж илгээнэ.
 */
class LoginCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Дорноговь ЗДТГ — нэвтрэх мэдээлэл',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.login-credentials',
            with: [
                'name' => $this->user->name,
                'login' => $this->user->phone ?: $this->user->email,
                'email' => $this->user->email,
                'password' => $this->temporaryPassword,
                'url' => route('login'),
            ],
        );
    }
}
