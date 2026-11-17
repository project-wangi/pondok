<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Rapor {{ $rapor['student']['nama'] }} {{ $rapor['class'] }}</title>
    @include('rapor-print-styles')
</head>
<body>
    <div class="toolbar">
        <span class="toolbar-note">Pilih ukuran kertas di dialog cetak</span>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <main class="sheet">
        @include('rapor-sheet', ['rapor' => $rapor])
    </main>

    <script>
        window.addEventListener('load', () => {
            setTimeout(() => window.print(), 250);
        });
    </script>
</body>
</html>
