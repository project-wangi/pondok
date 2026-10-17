<?php

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\SiteSettings\Pages\CreateSiteSetting;
use App\Filament\Resources\SiteSettings\Pages\EditSiteSetting;
use App\Filament\Resources\SiteSettings\SiteSettingResource;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

function schoolWebsiteAdmin(): User
{
    Role::create(['name' => 'admin', 'guard_name' => 'web']);

    return User::factory()->create()->assignRole('admin');
}

/** @return array<string, mixed> */
function schoolWebsitePostData(): array
{
    return [
        'title' => 'Kegiatan Belajar Santri',
        'slug' => 'kegiatan-belajar-santri',
        'category' => 'berita',
        'excerpt' => 'Informasi kegiatan belajar.',
        'body' => 'Kegiatan belajar dilaksanakan bersama para pengajar.',
        'is_published' => false,
        'published_at' => null,
    ];
}

/** @return array<string, mixed> */
function schoolWebsiteSettingsData(): array
{
    return [
        'school_name' => 'Pondok Ilmu',
        'tagline' => 'Belajar dengan akhlak',
        'hero_title' => 'Selamat Datang di Pondok Ilmu',
        'hero_description' => 'Informasi pendidikan untuk santri dan keluarga.',
        'about_title' => 'Mengenal Pondok Ilmu',
        'about_body' => 'Ruang belajar dan pembinaan akhlak bagi para santri.',
        'address' => 'Alamat sekretariat pondok',
        'email' => 'sekretariat@example.com',
        'phone' => '0211234567',
        'registration_label' => 'Hubungi Pendaftaran',
        'registration_url' => 'https://example.com/pendaftaran',
        'shortcuts' => [
            ['label' => 'Artikel Pondok', 'url' => '/artikel', 'icon' => 'heroicon-o-book-open'],
        ],
        'stats' => [
            ['value' => '12', 'label' => 'Kelas dalam data uji'],
        ],
    ];
}

test('the homepage renders the saved school identity and editable content', function () {
    $settings = SiteSetting::factory()->create(schoolWebsiteSettingsData());

    $response = $this->get(route('home'));

    $response->assertOk()->assertViewIs('school.home')
        ->assertViewHas('settings', fn (SiteSetting $actual): bool => $actual->is($settings))
        ->assertSee('Pondok Ilmu')
        ->assertSee('Belajar dengan akhlak')
        ->assertSee('Selamat Datang di Pondok Ilmu')
        ->assertSee('Informasi pendidikan untuk santri dan keluarga.')
        ->assertSee('Mengenal Pondok Ilmu')
        ->assertSee('Ruang belajar dan pembinaan akhlak bagi para santri.')
        ->assertSee('Hubungi Pendaftaran')
        ->assertSee('https://example.com/pendaftaran')
        ->assertSee('Artikel Pondok');
});

test('the homepage clearly labels missing photography without inventing a school image', function () {
    SiteSetting::factory()->create(['hero_image' => null, 'logo' => null]);

    $this->get(route('home'))->assertOk()
        ->assertSee('Ruang untuk foto sekolah')
        ->assertSee('Foto belum ditambahkan.')
        ->assertDontSee('<svg viewBox="0 0 640 480"', false);
});

test('the homepage uses uploaded school photography and logo with image error feedback', function () {
    SiteSetting::factory()->create([
        'hero_image' => 'website/school-photo.webp',
        'logo' => 'website/school-logo.png',
    ]);

    $this->get(route('home'))->assertOk()
        ->assertSee('/storage/website/school-photo.webp', false)
        ->assertSee('/storage/website/school-logo.png', false)
        ->assertSee('Foto sekolah tidak dapat ditampilkan.')
        ->assertDontSee('Ruang untuk foto sekolah');
});

test('the homepage omits unsafe saved links while retaining usable shortcuts', function () {
    SiteSetting::factory()->create([
        'registration_url' => 'javascript:alert(1)',
        'shortcuts' => [
            ['label' => 'Unsafe shortcut', 'url' => '//unsafe.example', 'icon' => 'heroicon-o-book-open'],
            ['label' => 'Artikel Pondok', 'url' => '/artikel', 'icon' => 'heroicon-o-book-open'],
        ],
    ]);

    $this->get(route('home'))->assertOk()
        ->assertSee('Artikel Pondok')
        ->assertDontSee('Unsafe shortcut')
        ->assertDontSee('javascript:alert(1)', false)
        ->assertDontSee('//unsafe.example', false);
});

test('public pages render unsaved defaults without creating settings or news', function (string $routeName) {
    $this->assertDatabaseCount('site_settings', 0);
    $this->assertDatabaseCount('posts', 0);

    $response = $this->get(route($routeName));

    $response->assertOk()->assertSee('Pondok Pesantren')
        ->assertViewHas('settings', function (SiteSetting $settings): bool {
            expect($settings->exists)->toBeFalse();
            expect($settings->school_name)->toBe('Pondok Pesantren');
            expect($settings->stats)->toBe([]);

            return true;
        });
    $this->assertDatabaseCount('site_settings', 0);
    $this->assertDatabaseCount('posts', 0);
})->with(['homepage' => 'home', 'article list' => 'posts.index']);

test('the homepage returns the latest four posts and three announcements and events', function () {
    $this->freezeTime();
    $news = Post::factory()->published()->count(5)->sequence(
        fn (Sequence $sequence): array => ['category' => 'berita', 'published_at' => now()->subMinutes($sequence->index + 1)],
    )->create();
    $announcements = Post::factory()->published()->count(4)->sequence(
        fn (Sequence $sequence): array => ['category' => 'pengumuman', 'published_at' => now()->subHours($sequence->index + 1)],
    )->create();
    $events = Post::factory()->published()->count(4)->sequence(
        fn (Sequence $sequence): array => ['category' => 'agenda', 'published_at' => now()->subDays($sequence->index + 1)],
    )->create();

    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertViewHas('posts', fn (Collection $posts): bool => $posts->modelKeys() === $news->take(4)->modelKeys())
        ->assertViewHas('announcements', fn (Collection $posts): bool => $posts->modelKeys() === $announcements->take(3)->modelKeys())
        ->assertViewHas('events', fn (Collection $posts): bool => $posts->modelKeys() === $events->take(3)->modelKeys());
});

test('public listings show published posts and hide ineligible posts', function (string $routeName, string $state, array $attributes) {
    $this->freezeTime();
    $published = Post::factory()->published()->create(['title' => 'Informasi yang sudah terbit']);
    $hidden = Post::factory()->{$state}()->create([
        'title' => 'Informasi yang belum boleh tampil',
        ...$attributes,
    ]);

    $response = $this->get(route($routeName));

    $response->assertOk()->assertSee($published->title)->assertDontSee($hidden->title)
        ->assertViewHas('posts', fn (Collection|LengthAwarePaginator $posts): bool => $posts->pluck('id')->all() === [$published->id]);
})->with(['homepage' => 'home', 'article list' => 'posts.index'])->with([
    'draft with a past date' => ['draft', ['published_at' => '2020-01-01 00:00:00']],
    'scheduled' => ['scheduled', []],
    'published without a date' => ['published', ['published_at' => null]],
]);

test('ineligible post detail pages return 404', function (string $state, array $attributes) {
    $this->freezeTime();
    $post = Post::factory()->{$state}()->create($attributes);

    $this->get(route('posts.show', ['post' => $post->slug]))->assertNotFound();
})->with([
    'draft with a past date' => ['draft', ['published_at' => '2020-01-01 00:00:00']],
    'scheduled' => ['scheduled', []],
    'published without a date' => ['published', ['published_at' => null]],
]);

test('a post published exactly now is accessible by slug with only the latest three eligible related posts', function () {
    $this->freezeTime();
    $settings = SiteSetting::factory()->create();
    $post = Post::factory()->published()->create([
        'published_at' => now(),
        'body' => 'Isi artikel yang dapat dibaca publik.',
    ]);
    $related = Post::factory()->published()->count(4)->sequence(
        fn (Sequence $sequence): array => ['published_at' => now()->subMinutes($sequence->index + 1)],
    )->create();
    Post::factory()->draft()->create();
    Post::factory()->scheduled()->create();
    Post::factory()->published()->create(['published_at' => null]);

    $response = $this->get(route('posts.show', ['post' => $post->slug]));

    $response->assertOk()->assertViewIs('school.posts.show')
        ->assertSee($post->title)->assertSee($post->body)
        ->assertViewHas('settings', fn (SiteSetting $actual): bool => $actual->is($settings))
        ->assertViewHas('post', fn (Post $actual): bool => $actual->is($post))
        ->assertViewHas('relatedPosts', fn (Collection $posts): bool => $posts->modelKeys() === $related->take(3)->modelKeys());
});

test('search matches each text field while preserving category and publication constraints', function (string $field) {
    $this->freezeTime();
    $content = ['title' => 'Kegiatan rutin', 'excerpt' => 'Informasi umum', 'body' => 'Catatan pembelajaran'];
    $match = Post::factory()->published()->create([
        ...$content, 'category' => 'berita', $field => 'Materi astronomi santri',
    ]);
    Post::factory()->published()->create([...$content, 'category' => 'berita']);
    Post::factory()->published()->create([
        ...$content, 'category' => 'agenda', $field => 'Materi astronomi santri',
    ]);
    Post::factory()->draft()->create([
        'category' => 'berita', 'title' => 'astronomi', 'excerpt' => 'astronomi', 'body' => 'astronomi',
    ]);
    Post::factory()->scheduled()->create(['category' => 'berita', 'body' => 'astronomi']);
    Post::factory()->published()->create(['category' => 'berita', 'excerpt' => 'astronomi', 'published_at' => null]);

    $response = $this->get(route('posts.index', ['q' => 'astronomi', 'category' => 'berita']));

    $response->assertOk()->assertViewIs('school.posts.index')
        ->assertViewHas('filters', ['q' => 'astronomi', 'category' => 'berita'])
        ->assertViewHas('posts', fn (LengthAwarePaginator $posts): bool => $posts->getCollection()->modelKeys() === [$match->id]);
})->with(['title', 'excerpt', 'body']);

test('the article list paginates nine posts and retains search and category on the next page', function () {
    $this->freezeTime();
    $posts = Post::factory()->published()->count(10)->sequence(
        fn (Sequence $sequence): array => [
            'title' => 'Jadwal belajar '.($sequence->index + 1),
            'category' => 'berita',
            'published_at' => now()->subMinutes($sequence->index + 1),
        ],
    )->create();
    $filters = ['q' => 'Jadwal', 'category' => 'berita'];

    $response = $this->get(route('posts.index', $filters));

    $response->assertOk()->assertViewHas('posts', function (LengthAwarePaginator $paginator) use ($posts): bool {
        expect($paginator->total())->toBe(10);
        expect($paginator->perPage())->toBe(9);
        expect($paginator->getCollection()->modelKeys())->toBe($posts->take(9)->modelKeys());
        parse_str((string) parse_url($paginator->url(2), PHP_URL_QUERY), $query);
        expect($query)->toMatchArray(['q' => 'Jadwal', 'category' => 'berita', 'page' => '2']);

        return true;
    });

    $this->get(route('posts.index', [...$filters, 'page' => 2]))->assertOk()
        ->assertViewHas('posts', fn (LengthAwarePaginator $paginator): bool => $paginator->getCollection()->modelKeys() === [$posts->last()->id]);
});

test('the article list rejects invalid search filters', function (array $query, string $field) {
    $this->getJson(route('posts.index', $query))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'overlong query' => [['q' => str_repeat('a', 151)], 'q'],
    'array query' => [['q' => ['astronomi']], 'q'],
    'unknown category' => [['category' => 'rahasia'], 'category'],
    'array category' => [['category' => ['berita']], 'category'],
]);

test('the homepage escapes editable school content', function (string $field) {
    $payload = '<script>alert("school-xss")</script>';
    SiteSetting::factory()->create([$field => $payload]);

    $this->get(route('home'))->assertOk()->assertSee($payload)->assertDontSee($payload, false);
})->with(['school_name', 'hero_title', 'hero_description', 'about_title', 'about_body']);

test('public cards escape post titles and excerpts', function (string $routeName) {
    $this->freezeTime();
    $title = '<script>alert("title-xss")</script>';
    $excerpt = '<img src=x onerror=alert("excerpt-xss")>';
    Post::factory()->published()->create(['title' => $title, 'excerpt' => $excerpt]);

    $this->get(route($routeName))->assertOk()
        ->assertSee($title)->assertDontSee($title, false)
        ->assertSee($excerpt)->assertDontSee($excerpt, false);
})->with(['homepage' => 'home', 'article list' => 'posts.index']);

test('post detail escapes its title and plain text body', function () {
    $this->freezeTime();
    $title = '<script>alert("title-xss")</script>';
    $body = '<script>alert("body-xss")</script>';
    $post = Post::factory()->published()->create(['title' => $title, 'body' => $body]);

    $this->get(route('posts.show', ['post' => $post->slug]))->assertOk()
        ->assertSee($title)->assertDontSee($title, false)
        ->assertSee($body)->assertDontSee($body, false);
});

test('guests are redirected from website administration to the admin login', function (string $routeName) {
    $this->get(route($routeName))->assertRedirectToRoute('filament.admin.auth.login');
})->with([
    'posts list' => 'filament.admin.resources.posts.index',
    'create post' => 'filament.admin.resources.posts.create',
    'settings list' => 'filament.admin.resources.site-settings.index',
    'create settings' => 'filament.admin.resources.site-settings.create',
]);

test('ordinary users cannot open website administration', function (string $routeName) {
    $this->actingAs(User::factory()->create());

    $this->get(route($routeName))->assertForbidden();
})->with([
    'posts list' => 'filament.admin.resources.posts.index',
    'create post' => 'filament.admin.resources.posts.create',
    'settings list' => 'filament.admin.resources.site-settings.index',
    'create settings' => 'filament.admin.resources.site-settings.create',
]);

test('ordinary users cannot mount website creation forms', function (string $component) {
    $this->actingAs(User::factory()->create());

    Livewire::test($component)->assertForbidden();

    $this->assertDatabaseCount('posts', 0);
    $this->assertDatabaseCount('site_settings', 0);
})->with(['post' => CreatePost::class, 'settings' => CreateSiteSetting::class]);

test('ordinary users cannot mount website edit forms', function (string $component, string $model) {
    $this->actingAs(User::factory()->create());
    $record = $model::factory()->create();
    $original = $record->fresh()->getAttributes();

    Livewire::test($component, ['record' => $record->getKey()])->assertForbidden();

    expect($record->fresh()->getAttributes())->toBe($original);
})->with([
    'post' => [EditPost::class, Post::class],
    'settings' => [EditSiteSetting::class, SiteSetting::class],
]);

test('an admin can create a draft through the actual post form', function () {
    $this->actingAs(schoolWebsiteAdmin());
    $data = schoolWebsitePostData();

    Livewire::test(CreatePost::class)->fillForm($data)->call('create')
        ->assertHasNoFormErrors()->assertRedirect(PostResource::getUrl('index'));

    $this->assertDatabaseCount('posts', 1);
    $this->assertDatabaseHas('posts', $data);
});

test('an admin can edit and publish a post without changing its unique slug', function () {
    $this->freezeTime();
    $this->actingAs(schoolWebsiteAdmin());
    $post = Post::factory()->draft()->create(['slug' => 'kegiatan-belajar-santri']);
    $data = [
        ...schoolWebsitePostData(),
        'title' => 'Kegiatan Belajar Terbaru',
        'category' => 'pengumuman',
        'excerpt' => 'Ringkasan terbaru.',
        'body' => 'Isi artikel yang telah diperbarui.',
        'is_published' => true,
        'published_at' => now()->subHour()->startOfMinute()->format('Y-m-d H:i:s'),
    ];

    Livewire::test(EditPost::class, ['record' => $post->getKey()])->fillForm($data)->call('save')
        ->assertHasNoFormErrors()->assertRedirect(PostResource::getUrl('index'));

    $this->assertDatabaseCount('posts', 1);
    $this->assertDatabaseHas('posts', ['id' => $post->id, ...$data]);
});

test('the post form requires a title slug category and body', function () {
    $this->actingAs(schoolWebsiteAdmin());

    Livewire::test(CreatePost::class)->fillForm([
        'title' => '', 'slug' => '', 'category' => null, 'body' => '',
    ])->call('create')->assertHasFormErrors([
        'title' => 'required', 'slug' => 'required', 'category' => 'required', 'body' => 'required',
    ]);

    $this->assertDatabaseCount('posts', 0);
});

test('the post form rejects a duplicate slug on creation', function () {
    $this->actingAs(schoolWebsiteAdmin());
    $existing = Post::factory()->draft()->create(['slug' => 'kegiatan-belajar-santri']);

    Livewire::test(CreatePost::class)->fillForm(schoolWebsitePostData())->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);

    $this->assertDatabaseCount('posts', 1);
    $this->assertModelExists($existing);
});

test('the post form cannot replace another posts slug during editing', function () {
    $this->actingAs(schoolWebsiteAdmin());
    $existing = Post::factory()->draft()->create(['slug' => 'kegiatan-belajar-santri']);
    $post = Post::factory()->draft()->create(['slug' => 'slug-asli']);

    Livewire::test(EditPost::class, ['record' => $post->getKey()])
        ->fillForm(schoolWebsitePostData())->call('save')->assertHasFormErrors(['slug' => 'unique']);

    $this->assertDatabaseCount('posts', 2);
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'slug' => 'slug-asli']);
    $this->assertDatabaseHas('posts', ['id' => $existing->id, 'slug' => 'kegiatan-belajar-santri']);
});

test('the post form rejects invalid slugs and categories', function (string $field, string $value, string $rule) {
    $this->actingAs(schoolWebsiteAdmin());

    Livewire::test(CreatePost::class)->fillForm([...schoolWebsitePostData(), $field => $value])
        ->call('create')->assertHasFormErrors([$field => $rule]);

    $this->assertDatabaseCount('posts', 0);
})->with([
    'unsafe slug' => ['slug', '../artikel', 'regex'],
    'unknown category' => ['category', 'rahasia', 'in'],
]);

test('publishing a post requires a publication date', function () {
    $this->actingAs(schoolWebsiteAdmin());

    Livewire::test(CreatePost::class)->fillForm([
        ...schoolWebsitePostData(), 'is_published' => true, 'published_at' => null,
    ])->call('create')->assertHasFormErrors(['published_at' => 'required_if']);

    $this->assertDatabaseCount('posts', 0);
});

test('an admin can create the singleton settings with shortcuts and stats', function () {
    $this->actingAs(schoolWebsiteAdmin());
    $data = schoolWebsiteSettingsData();

    Livewire::test(CreateSiteSetting::class)->fillForm($data)->call('create')
        ->assertHasNoFormErrors()->assertRedirect(SiteSettingResource::getUrl('index'));

    $this->assertDatabaseCount('site_settings', 1);
    $this->assertDatabaseHas('site_settings', [
        'id' => 1, 'school_name' => 'Pondok Ilmu', 'registration_url' => 'https://example.com/pendaftaran',
    ]);
    $settings = SiteSetting::query()->findOrFail(1);
    expect($settings->shortcuts)->toBe($data['shortcuts']);
    expect($settings->stats)->toBe($data['stats']);
});

test('an admin can edit the singleton without creating a second settings row', function () {
    $this->actingAs(schoolWebsiteAdmin());
    $settings = SiteSetting::factory()->create();
    $data = [
        ...schoolWebsiteSettingsData(),
        'school_name' => 'Pondok Ilmu Baru',
        'registration_url' => '/artikel',
        'shortcuts' => [
            ['label' => 'Tentang Pondok', 'url' => '#tentang', 'icon' => 'heroicon-o-information-circle'],
        ],
        'stats' => [],
    ];

    Livewire::test(EditSiteSetting::class, ['record' => $settings->getKey()])->fillForm($data)->call('save')
        ->assertHasNoFormErrors()->assertRedirect(SiteSettingResource::getUrl('index'));

    $this->assertDatabaseCount('site_settings', 1);
    $this->assertDatabaseHas('site_settings', ['id' => 1, 'school_name' => 'Pondok Ilmu Baru', 'registration_url' => '/artikel']);
    expect($settings->fresh()->shortcuts)->toBe($data['shortcuts']);
    expect($settings->fresh()->stats)->toBe([]);
});

test('the settings creation form forbids a second singleton even for an admin', function () {
    $this->actingAs(schoolWebsiteAdmin());
    $settings = SiteSetting::factory()->create(['school_name' => 'Pengaturan Asli']);

    Livewire::test(CreateSiteSetting::class)->assertForbidden();

    $this->assertDatabaseCount('site_settings', 1);
    $this->assertDatabaseHas('site_settings', ['id' => $settings->id, 'school_name' => 'Pengaturan Asli']);
});

test('the settings form rejects unsafe registration and shortcut URLs', function (string $field, string $url) {
    $this->actingAs(schoolWebsiteAdmin());
    $data = schoolWebsiteSettingsData();
    data_set($data, $field, $url);

    $component = Livewire::test(CreateSiteSetting::class)->fillForm($data)->call('create')
        ->assertHasFormErrors([$field]);

    expect($component->errors()->get('data.'.$field))
        ->toContain('Tautan harus menggunakan http://, https://, /halaman, atau #bagian.');

    $this->assertDatabaseCount('site_settings', 0);
})->with(['registration' => 'registration_url', 'shortcut' => 'shortcuts.0.url'])->with([
    'javascript' => 'javascript:alert(1)',
    'mixed case javascript' => 'JaVaScRiPt:alert(1)',
    'data URL' => 'data:text/html,<script>alert(1)</script>',
    'protocol relative' => '//example.com/pendaftaran',
    'backslash authority' => '/\\example.com',
    'embedded newline' => "/\n/example.com",
]);

test('the settings form accepts supported safe URL types', function (string $url) {
    $this->actingAs(schoolWebsiteAdmin());

    Livewire::test(CreateSiteSetting::class)->fillForm([
        ...schoolWebsiteSettingsData(),
        'registration_url' => $url,
        'shortcuts' => [['label' => 'Informasi', 'url' => $url, 'icon' => 'heroicon-o-megaphone']],
    ])->call('create')->assertHasNoFormErrors()->assertRedirect(SiteSettingResource::getUrl('index'));

    $this->assertDatabaseCount('site_settings', 1);
    $this->assertDatabaseHas('site_settings', ['id' => 1, 'registration_url' => $url]);
    expect(SiteSetting::query()->findOrFail(1)->shortcuts[0]['url'])->toBe($url);
})->with([
    'https' => 'https://example.com/pendaftaran',
    'http' => 'http://example.com/pendaftaran',
    'root relative' => '/artikel',
    'anchor' => '#pendaftaran',
]);

test('the settings form also rejects unsafe URLs during editing without changing persisted data', function () {
    $this->actingAs(schoolWebsiteAdmin());
    $settings = SiteSetting::factory()->create(['registration_url' => '/artikel']);

    Livewire::test(EditSiteSetting::class, ['record' => $settings->getKey()])
        ->fillForm(['registration_url' => 'javascript:alert(1)'])->call('save')
        ->assertHasFormErrors(['registration_url']);

    $this->assertDatabaseCount('site_settings', 1);
    $this->assertDatabaseHas('site_settings', ['id' => 1, 'registration_url' => '/artikel']);
});

test('the settings form limits shortcut and statistic counts', function (string $field, int $count) {
    $this->actingAs(schoolWebsiteAdmin());
    $data = schoolWebsiteSettingsData();
    $data[$field] = array_fill(0, $count, $data[$field][0]);

    Livewire::test(CreateSiteSetting::class)->fillForm($data)->call('create')
        ->assertHasFormErrors([$field => 'max']);

    $this->assertDatabaseCount('site_settings', 0);
})->with(['eleven shortcuts' => ['shortcuts', 11], 'five stats' => ['stats', 5]]);

test('the settings form rejects icons outside the shortcut allowlist', function () {
    $this->actingAs(schoolWebsiteAdmin());
    $data = schoolWebsiteSettingsData();
    $data['shortcuts'][0]['icon'] = 'heroicon-o-command-line';

    Livewire::test(CreateSiteSetting::class)->fillForm($data)->call('create')
        ->assertHasFormErrors(['shortcuts.0.icon' => 'in']);

    $this->assertDatabaseCount('site_settings', 0);
});
