@php
    use App\Models\SiteSetting;
    use Illuminate\Support\Facades\Storage;

    $settings = SiteSetting::current();
    $schoolName = (string) ($settings->school_name ?: config('app.name'));
    $logo = filament()->getBrandLogo();
    $tagline = (string) ($settings->tagline ?? '');
    $address = (string) ($settings->address ?? '');

    $photoPath = collect([
        'images/login-pondok.jpg',
        'images/login-pondok.jpeg',
        'images/login-pondok.png',
        'images/login-pondok.webp',
    ])->first(fn (string $path): bool => file_exists(public_path($path)));

    $photo = $photoPath !== null
        ? asset($photoPath)
        : (filled($settings->hero_image) ? Storage::disk('public')->url($settings->hero_image) : null);
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div class="auth-shell">
        <aside
            @class(['auth-visual', 'auth-visual-fallback' => blank($photo)])
            @if (filled($photo)) style="--auth-photo: url('{{ $photo }}');" @endif
        >
            <div class="auth-visual-inner">
                <div class="auth-brand auth-brand-light">
                    @if (filled($logo))
                        <img src="{{ $logo }}" alt="">
                    @endif
                    <span>{{ $schoolName }}</span>
                </div>

                <div class="auth-visual-copy">
                    <p class="auth-eyebrow">SIAKAD</p>
                    <p class="auth-visual-title">{{ $schoolName }}</p>
                    @if ($tagline !== '' || $address !== '')
                        <p class="auth-visual-text">
                            {{ collect([$tagline, $address])->filter()->implode(' · ') }}
                        </p>
                    @endif
                </div>
            </div>
        </aside>

        <main class="auth-main">
            <div class="auth-panel">
                <div class="auth-brand">
                    @if (filled($logo))
                        <img src="{{ $logo }}" alt="">
                    @endif
                    <span>{{ $schoolName }}</span>
                </div>

                {{ $slot }}
            </div>
        </main>
    </div>
</x-filament-panels::layout.base>
