<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Slip Gaji Semua Guru {{ $periode }}</title>
    @include('slip-gaji-print-styles')
</head>
<body>
    <div class="toolbar toolbar-split">
        <span class="toolbar-count">{{ count($slips) }} slip · {{ $periode }} · pilih ukuran kertas di dialog cetak</span>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    @forelse ($slips as $slip)
        <div class="sheet">
            @include('slip-gaji-sheet', $slip)
        </div>
    @empty
        <p class="note">Belum ada guru yang dapat dicetak pada periode ini.</p>
    @endforelse

    @if ($slips !== [])
        <script>
            window.addEventListener('load', () => {
                setTimeout(() => window.print(), 250);
            });
        </script>
    @endif
</body>
</html>
