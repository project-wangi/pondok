<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('admin panel login is written in indonesian', function () {
    $this->get(route('filament.admin.auth.login'))
        ->assertOk()
        ->assertSee('Masuk ke akun Anda')
        ->assertSee('Alamat email')
        ->assertSee('Ingat saya');
});

test('student panel login is written in indonesian', function () {
    $this->get(route('filament.siswa.auth.login'))
        ->assertOk()
        ->assertSee('Masuk ke akun Anda')
        ->assertSee('Daftar sebagai calon murid')
        ->assertSee('Lupa kata sandi?');
});

test('admin panel chrome is written in indonesian', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin);

    $this->get('/admin')
        ->assertOk()
        ->assertSee('Dasbor');
});

test('validation messages are written in indonesian', function () {
    expect(__('validation.required', ['attribute' => 'Nama']))->toBe('Nama wajib diisi.')
        ->and(__('validation.email', ['attribute' => 'Email']))->toBe('Email harus berupa alamat email yang valid.')
        ->and(__('validation.unique', ['attribute' => 'Email']))->toBe('Email sudah digunakan.')
        ->and(__('validation.digits', ['attribute' => 'NIK', 'digits' => 16]))->toBe('NIK harus terdiri dari 16 angka.')
        ->and(__('auth.failed'))->toBe('Email atau kata sandi yang Anda masukkan tidak cocok.');
});
