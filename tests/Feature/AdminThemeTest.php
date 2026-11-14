<?php

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

uses(LazilyRefreshDatabase::class);

test('the admin login page includes the green theme stylesheet', function () {
    $this->mock(Vite::class)
        ->shouldReceive('__invoke')
        ->once()
        ->with('resources/css/filament/admin/theme.css')
        ->andReturn(new HtmlString('<link rel="stylesheet" href="/admin-green-theme.css">'));

    $response = $this->get(route('filament.admin.auth.login'));

    $response->assertOk()
        ->assertSee('<link rel="stylesheet" href="/admin-green-theme.css">', false);
});

test('the admin panel brand uses the school logo instead of the app name', function () {
    $this->withoutVite();
    Storage::fake('public');
    Storage::disk('public')->put('logo-sekolah.png', 'fake-image');

    SiteSetting::create(['school_name' => 'Pondok Ilmu', 'logo' => 'logo-sekolah.png']);

    $this->get(route('filament.admin.auth.login'))
        ->assertOk()
        ->assertSee('Pondok Ilmu')
        ->assertSee('logo-sekolah.png');
});

test('the admin panel falls back to the school name when no logo is set', function () {
    $this->withoutVite();

    SiteSetting::create(['school_name' => 'Pondok Tanpa Logo', 'logo' => null]);

    $this->get(route('filament.admin.auth.login'))
        ->assertOk()
        ->assertSee('Pondok Tanpa Logo')
        ->assertDontSee('>Laravel<', false);
});
