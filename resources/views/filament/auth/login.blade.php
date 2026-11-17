@php
    $brandName = filament()->getBrandName();
@endphp

<div class="auth-form">
    <div class="auth-card">
        <header class="auth-form-head">
            <p class="auth-eyebrow">SIAKAD</p>
            <h1 class="auth-heading">{{ $this->getHeading() }}</h1>
            @if (filled($subheading = $this->getSubheading()))
                <p class="auth-subheading">{{ $subheading }}</p>
            @endif
        </header>

        {{ $this->content }}
    </div>

    <p class="auth-footnote">© {{ now()->year }} {{ $brandName }}</p>

    <x-filament-actions::modals />
</div>
