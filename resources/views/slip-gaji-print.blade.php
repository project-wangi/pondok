<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Slip Gaji {{ $rincian['periode'] }}</title>
    @include('slip-gaji-print-styles')
</head>
<body>
    <div class="sheet">
        <div class="toolbar toolbar-split">
            <span class="toolbar-count">Pilih ukuran kertas di dialog cetak</span>
            <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        </div>

        @include('slip-gaji-sheet')
    </div>

    <script>
        window.addEventListener('load', () => {
            setTimeout(() => window.print(), 250);
        });
    </script>
</body>
</html>
