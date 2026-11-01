@extends('school.layout')

@section('content')
    <section class="school-page-header">
        <div class="school-container school-page-header-inner">
            <nav aria-label="Breadcrumb" class="school-breadcrumb">
                <a href="{{ route('home') }}">Beranda</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Pendaftaran Berhasil</span>
            </nav>
            <p class="school-eyebrow mt-5">Penerimaan Santri Baru</p>
            <h1 class="school-page-title">Pendaftaran Berhasil Dikirim</h1>
            <p class="school-page-lead">Simpan nomor pendaftaran berikut untuk keperluan verifikasi.</p>
        </div>
    </section>

    <section class="school-container school-section">
        <div class="mx-auto max-w-2xl rounded-xl border border-[var(--school-line)] bg-[var(--school-white)] p-6 md:p-8">
            <p class="text-sm font-semibold uppercase tracking-wide text-[var(--school-muted)]">Nomor Pendaftaran</p>
            <p class="mt-1 text-2xl font-bold text-[var(--school-ink)]">{{ $nomor }}</p>

            @if ($mailSent)
                <p class="mt-5 text-[var(--school-ink)]">
                    Akun login telah dikirim ke <strong>{{ $email }}</strong>. Cek inbox atau folder spam,
                    lalu masuk untuk melengkapi data administrasi dan memantau status seleksi.
                </p>
            @else
                <div role="alert" class="school-alert mt-5">
                    Pendaftaran tersimpan, tetapi email akun gagal dikirim. Hubungi admin sekolah
                    @if ($settings->email)
                        di <a href="mailto:{{ $settings->email }}" class="underline">{{ $settings->email }}</a>
                    @elseif ($settings->phone)
                        di {{ $settings->phone }}
                    @endif
                    untuk mendapatkan akun login.
                </div>
            @endif

            <div class="mt-6 rounded-lg border border-[var(--school-line)] bg-[var(--school-primary-soft)] p-4 text-sm leading-6 text-[var(--school-ink)]">
                <p class="font-semibold">Langkah berikutnya</p>
                <p class="mt-1 text-[var(--school-muted)]">
                    Setelah masuk ke panel siswa, buka menu <strong>Data Pendaftaran</strong> untuk mengisi NIK, KK,
                    data orang tua, alamat, serta mengunggah Kartu Keluarga dan ijazah.
                    Pendaftaran dinyatakan lengkap setelah semua bagian wajib terisi.
                </p>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('filament.siswa.auth.login') }}" class="school-button school-button-primary">Masuk ke Akun</a>
                <a href="{{ route('home') }}" class="school-text-link self-center">Kembali ke beranda</a>
            </div>
        </div>
    </section>
@endsection
