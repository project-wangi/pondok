<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
