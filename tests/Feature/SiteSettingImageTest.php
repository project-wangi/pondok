<?php

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

test('website images use the r2 disk', function () {
    config(['filesystems.disks.r2.url' => 'https://cdn.example.com']);

    $settings = SiteSetting::factory()->make([
        'logo' => 'website/logo.png',
        'hero_image' => 'website/hero.jpg',
    ]);

    expect($settings->logoUrl())->toBe('https://cdn.example.com/website/logo.png')
        ->and($settings->heroImageUrl())->toBe('https://cdn.example.com/website/hero.jpg');
});

test('blank website images have no url', function () {
    config(['filesystems.disks.r2.url' => 'https://cdn.example.com']);

    $settings = SiteSetting::factory()->make([
        'logo' => null,
        'hero_image' => null,
    ]);

    expect($settings->logoUrl())->toBeNull()
        ->and($settings->heroImageUrl())->toBeNull();
});

test('replacing a website image deletes the old r2 file', function (string $attribute) {
    Storage::fake(SiteSetting::IMAGE_DISK);

    $settings = SiteSetting::factory()->create([$attribute => 'website/old.png']);
    Storage::disk(SiteSetting::IMAGE_DISK)->put('website/old.png', 'old-image');

    $settings->update([$attribute => 'website/new.png']);

    Storage::disk(SiteSetting::IMAGE_DISK)->assertMissing('website/old.png');
})->with(['logo', 'hero_image']);

test('clearing a website image deletes the r2 file', function () {
    Storage::fake(SiteSetting::IMAGE_DISK);

    $settings = SiteSetting::factory()->create(['logo' => 'website/logo.png']);
    Storage::disk(SiteSetting::IMAGE_DISK)->put('website/logo.png', 'logo');

    $settings->update(['logo' => null]);

    Storage::disk(SiteSetting::IMAGE_DISK)->assertMissing('website/logo.png');
});

test('keeping the same website image keeps the r2 file', function () {
    Storage::fake(SiteSetting::IMAGE_DISK);

    $settings = SiteSetting::factory()->create(['logo' => 'website/logo.png']);
    Storage::disk(SiteSetting::IMAGE_DISK)->put('website/logo.png', 'logo');

    $settings->update(['tagline' => 'Slogan baru']);

    Storage::disk(SiteSetting::IMAGE_DISK)->assertExists('website/logo.png');
});
