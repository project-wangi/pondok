<?php

namespace App\Mail;

use App\Models\CalonSiswa;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KataSandiBaruCalonSiswa extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public CalonSiswa $calon,
        public string $password,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kata Sandi Baru Akun Pendaftaran - '.SiteSetting::current()->school_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.kata-sandi-baru-calon-siswa',
            with: ['schoolName' => SiteSetting::current()->school_name],
        );
    }
}
