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

class PendaftaranCalonSiswa extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public CalonSiswa $calon,
        public string $password,
        public string $tautanMasuk,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Akun Pendaftaran Calon Siswa - '.SiteSetting::current()->school_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pendaftaran-calon-siswa',
            with: [
                'schoolName' => SiteSetting::current()->school_name,
                'berlakuHari' => CalonSiswa::TAUTAN_MASUK_HARI,
            ],
        );
    }
}
