<?php

namespace App\Http\Controllers;

use App\Filament\Siswa\Pages\StatusPendaftaran;
use App\Filament\Siswa\Pages\UbahKataSandi;
use App\Mail\PendaftaranCalonSiswa;
use App\Models\CalonSiswa;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class PendaftaranController extends Controller
{
    public function create(): View
    {
        return view('school.pendaftaran.create', [
            'settings' => SiteSetting::current(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'nama' => trim((string) $request->input('nama')),
            'email' => Str::lower(trim((string) $request->input('email'))),
            'nohp' => trim((string) $request->input('nohp')),
        ]);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', Rule::in(array_keys(CalonSiswa::JENIS_KELAMIN))],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email', 'unique:calon_siswas,email'],
            'nohp' => ['required', 'string', 'regex:/^0[0-9]{8,14}$/'],
        ], [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'nama.max' => 'Nama lengkap maksimal 255 karakter.',
            'jenis_kelamin.required' => 'Jenis kelamin wajib dipilih.',
            'jenis_kelamin.in' => 'Jenis kelamin tidak valid.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'nohp.required' => 'Nomor WhatsApp wajib diisi.',
            'nohp.regex' => 'Nomor WhatsApp harus diawali 0 dan hanya berisi angka (9 sampai 15 digit).',
        ]);

        $password = Str::password(10, symbols: false);

        [$user, $calon] = DB::transaction(function () use ($data, $password): array {
            $user = User::query()->create([
                'name' => $data['nama'],
                'email' => $data['email'],
                'password' => $password,
            ]);

            $calon = CalonSiswa::query()->create([
                'user_id' => $user->id,
                'nomor_pendaftaran' => 'PPDB-'.now()->format('Y').'-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'nama' => $data['nama'],
                'jenis_kelamin' => $data['jenis_kelamin'],
                'email' => $data['email'],
                'nohp' => $data['nohp'],
                'status' => CalonSiswa::STATUS_MENUNGGU,
            ]);

            return [$user, $calon];
        });

        $mailSent = true;
        $tautanMasuk = route('pendaftaran.masuk.tautan', $calon->buatTautanMasuk());

        try {
            Mail::to($user->email)->send(new PendaftaranCalonSiswa($user, $calon, $password, $tautanMasuk));
        } catch (Throwable $exception) {
            report($exception);
            $mailSent = false;
        }

        return redirect()
            ->route('pendaftaran.sukses')
            ->with([
                'pendaftaran_nomor' => $calon->nomor_pendaftaran,
                'pendaftaran_email' => $calon->email,
                'mail_sent' => $mailSent,
            ]);
    }

    public function sukses(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('pendaftaran_nomor')) {
            return redirect()->route('pendaftaran.create');
        }

        return view('school.pendaftaran.sukses', [
            'settings' => SiteSetting::current(),
            'nomor' => $request->session()->get('pendaftaran_nomor'),
            'email' => $request->session()->get('pendaftaran_email'),
            'mailSent' => (bool) $request->session()->get('mail_sent', true),
        ]);
    }

    public function masukLewatTautan(Request $request, string $token): RedirectResponse
    {
        $calon = CalonSiswa::cariLewatTautanMasuk($token);
        $user = $calon?->user;

        if ($calon === null || $user === null) {
            return redirect()
                ->to(route('filament.siswa.auth.login'))
                ->withErrors([
                    'email' => 'Tautan masuk sudah tidak berlaku. Silakan masuk dengan email dan kata sandi Anda.',
                ]);
        }

        $calon->hapusTautanMasuk();

        Auth::login($user);
        $request->session()->regenerate();

        if ($request->query('lanjut') === 'sandi') {
            return redirect()->to(UbahKataSandi::getUrl(panel: 'siswa'));
        }

        return redirect()->to(StatusPendaftaran::getUrl(panel: 'siswa'));
    }
}
