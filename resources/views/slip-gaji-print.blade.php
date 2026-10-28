<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Slip Gaji {{ $rincian['periode'] }}</title>
    <style>
        :root {
            color-scheme: light;
        }

        @page {
            size: A4 portrait;
            margin: 14mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #111827;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }

        .sheet {
            width: 100%;
            max-width: 186mm;
            margin: 0 auto;
        }

        .toolbar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 12px;
        }

        .toolbar button {
            padding: 8px 14px;
            border: 1px solid #9ca3af;
            border-radius: 6px;
            background: #f3f4f6;
            color: #111827;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid #111827;
        }

        .school {
            margin: 0;
            color: #374151;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 4px 0 0;
            font-size: 20px;
            line-height: 1.15;
        }

        .period {
            text-align: right;
        }

        .period span {
            display: block;
            color: #374151;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .period strong {
            display: block;
            margin-top: 2px;
            font-size: 14px;
        }

        .identity {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px 18px;
            margin: 14px 0;
        }

        .identity div {
            min-width: 0;
        }

        dt {
            color: #374151;
            font-size: 10px;
        }

        dd {
            margin: 2px 0 0;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 6px 8px;
            border: 1px solid #9ca3af;
            text-align: left;
            vertical-align: top;
        }

        thead th {
            background: #e5e7eb;
            color: #111827;
            font-weight: 700;
            text-align: center;
        }

        tbody td:first-child {
            font-weight: 600;
        }

        tbody td:nth-child(2) {
            color: #374151;
        }

        tbody td:last-child,
        thead th:last-child {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        tfoot th,
        tfoot td {
            background: #e5e7eb;
            font-weight: 700;
        }

        tfoot th {
            text-align: right;
        }

        tfoot td {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .note {
            margin-top: 12px;
            color: #4b5563;
            font-size: 10px;
            line-height: 1.6;
        }

        @media print {
            .toolbar {
                display: none !important;
            }
        }

        @media (max-width: 640px) {
            .identity {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar">
            <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        </div>

        <header>
            <div>
                <p class="school">{{ $sekolah }}</p>
                <h1>Slip Gaji Guru</h1>
            </div>
            <div class="period">
                <span>Periode</span>
                <strong>{{ $rincian['periode'] }}</strong>
            </div>
        </header>

        <dl class="identity">
            <div>
                <dt>Nama guru</dt>
                <dd>{{ $rincian['nama'] }}</dd>
            </div>
            <div>
                <dt>Jarak rumah</dt>
                <dd>{{ $angka($rincian['jarak_rumah']) }} km</dd>
            </div>
            <div>
                <dt>JP mengajar</dt>
                <dd>{{ $angka($rincian['jp']) }} JP</dd>
            </div>
            <div>
                <dt>Hari hadir</dt>
                <dd>{{ $rincian['hari_hadir'] }} hari</dd>
            </div>
        </dl>

        <table>
            <thead>
                <tr>
                    <th scope="col">Komponen</th>
                    <th scope="col">Rincian</th>
                    <th scope="col">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($komponen as $item)
                    <tr>
                        <td>{{ $item['label'] }}</td>
                        <td>{{ $item['rincian'] }}</td>
                        <td>{{ $item['jumlah'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row" colspan="2">Total diterima</th>
                    <td>{{ $rupiah($rincian['total']) }}</td>
                </tr>
            </tfoot>
        </table>

        <p class="note">
            Slip ini dihitung otomatis dari sesi mengajar yang sudah ada presensi siswa,
            kehadiran rapat, hari piket, tunjangan jabatan, dan peminatan pada periode tersebut.
            Dicetak {{ $dicetak->locale('id')->translatedFormat('d F Y H:i') }}.
        </p>
    </div>

    <script>
        window.addEventListener('load', () => {
            setTimeout(() => window.print(), 250);
        });
    </script>
</body>
</html>
