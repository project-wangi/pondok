@extends('school.layout')

@section('content')
    <section class="school-page-header">
        <div class="school-container school-page-header-inner">
            <nav aria-label="Breadcrumb" class="school-breadcrumb">
                <a href="{{ route('home') }}">Beranda</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Pendaftaran</span>
            </nav>
            <p class="school-eyebrow mt-5">Penerimaan Santri Baru</p>
            <h1 class="school-page-title">Pendaftaran Calon Murid Baru</h1>
            <p class="school-page-lead">
                Isi data singkat berikut untuk membuat akun pendaftaran. Akun login dikirim ke email Anda,
                lalu data administrasi dan dokumen dilengkapi dari dalam akun tersebut.
            </p>
        </div>
    </section>

    <section class="school-container school-section">
        <div class="mx-auto max-w-2xl">
            @if ($errors->any())
                <div role="alert" class="school-alert mb-5">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('pendaftaran.store') }}" method="POST" class="grid gap-5 rounded-xl border border-[var(--school-line)] bg-[var(--school-white)] p-5 md:p-7">
                @csrf

                <div class="school-field">
                    <label for="nama">Nama Lengkap Calon Murid Baru</label>
                    <input
                        id="nama"
                        name="nama"
                        type="text"
                        value="{{ old('nama') }}"
                        required
                        maxlength="255"
                        autocomplete="name"
                        aria-describedby="nama-hint"
                        @error('nama') aria-invalid="true" @enderror
                    >
                    <p id="nama-hint" class="text-sm leading-6 text-[var(--school-muted)]">Masukkan Nama Lengkap Sesuai Akta Kelahiran.</p>
                    @error('nama')
                        <p class="text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="school-field">
                    <label for="jenis_kelamin">Jenis Kelamin Calon Murid Baru</label>
                    <select id="jenis_kelamin" name="jenis_kelamin" required @error('jenis_kelamin') aria-invalid="true" @enderror>
                        <option value="">Pilih jenis kelamin</option>
                        @foreach (App\Models\CalonSiswa::JENIS_KELAMIN as $value => $label)
                            <option value="{{ $value }}" @selected(old('jenis_kelamin') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('jenis_kelamin')
                        <p class="text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="school-field">
                    <label for="email">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        maxlength="255"
                        autocomplete="email"
                        aria-describedby="email-hint"
                        @error('email') aria-invalid="true" @enderror
                    >
                    <p id="email-hint" class="text-sm leading-6 text-[var(--school-muted)]">
                        Masukkan Alamat Email yang Valid. Akun login akan dikirim ke email ini.
                    </p>
                    @error('email')
                        <p class="text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="school-field">
                    <label for="nohp">Nomor WhatsApp Pendaftar</label>
                    <input
                        id="nohp"
                        name="nohp"
                        type="tel"
                        inputmode="numeric"
                        pattern="0[0-9]{8,14}"
                        value="{{ old('nohp') }}"
                        required
                        maxlength="20"
                        placeholder="Contoh: 081234567891"
                        autocomplete="tel"
                        oninput="this.value = this.value.replace(/\D/g, '')"
                        aria-describedby="nohp-hint"
                        @error('nohp') aria-invalid="true" @enderror
                    >
                    <div id="nohp-hint" class="text-sm leading-6 text-[var(--school-muted)]">
                        <p>Masukkan Nomor Sesuai Ketentuan Berikut:</p>
                        <ul class="mt-1 list-disc pl-5">
                            <li>Diawali dengan angka (0), tanpa spasi, tanpa strip (-).</li>
                            <li>Nomor WA akan digunakan untuk semua proses pendaftaran dan verifikasi calon murid.</li>
                        </ul>
                    </div>
                    @error('nohp')
                        <p class="text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="border-t border-[var(--school-line)] pt-5">
                    <p class="text-sm font-semibold text-[var(--school-ink)]">Setelah akun dibuat</p>
                    <ol class="mt-2 grid list-decimal gap-1 pl-5 text-sm leading-6 text-[var(--school-muted)]">
                        <li>Masuk dengan tautan atau kata sandi dari email pendaftaran.</li>
                        <li>Lengkapi data administrasi: NIK, KK, data orang tua, dan alamat.</li>
                        <li>Unggah Kartu Keluarga, ijazah, dan dokumen pendukung lain.</li>
                        <li>Status pendaftaran menjadi lengkap setelah semua data wajib terisi.</li>
                    </ol>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="school-button school-button-primary justify-center">
                        Buat Akun Pendaftaran
                    </button>
                    <a href="{{ route('filament.siswa.auth.login') }}" class="school-text-link">Sudah punya akun? Masuk</a>
                </div>
            </form>
        </div>
    </section>
@endsection
