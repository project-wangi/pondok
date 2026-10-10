<?php

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

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
