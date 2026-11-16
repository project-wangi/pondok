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
    kehadiran rapat, hari piket, tunjangan jabatan, dan peminatan, dikurangi potongan
    donasi pada periode tersebut.
    Dicetak {{ $dicetak->locale('id')->translatedFormat('d F Y H:i') }}.
</p>

<div class="signatures">
    <div>
        <p class="sign-role">Bendahara</p>
        <div class="sign-space" aria-hidden="true"></div>
        <p class="sign-name">
            @if (filled($bendahara))
                {{ $bendahara }}
            @else
                <span class="sr-only">Nama dan tanda tangan Bendahara</span>
            @endif
        </p>
    </div>

    <div>
        <p class="sign-role">Kepala Yayasan</p>
        <div class="sign-space" aria-hidden="true"></div>
        <p class="sign-name">
            @if (filled($kepalaYayasan))
                {{ $kepalaYayasan }}
            @else
                <span class="sr-only">Nama dan tanda tangan Kepala Yayasan</span>
            @endif
        </p>
    </div>
</div>
