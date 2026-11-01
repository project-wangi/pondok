<?php

namespace App\Models;

use Database\Factories\CalonSiswaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CalonSiswa extends Model
{
    /** @use HasFactory<CalonSiswaFactory> */
    use HasFactory;

    public const STATUS_MENUNGGU = 'Menunggu';

    public const STATUS_LULUS = 'Lulus';

    public const STATUS_TIDAK_LULUS = 'Tidak Lulus';

    public const STATUS_DITERIMA = 'Diterima';

    public const TAUTAN_MASUK_HARI = 7;

    /** @var array<string, string> */
    public const STATUS = [
        self::STATUS_MENUNGGU => 'Menunggu',
        self::STATUS_LULUS => 'Lulus',
        self::STATUS_TIDAK_LULUS => 'Tidak Lulus',
        self::STATUS_DITERIMA => 'Diterima',
    ];

    /** @var array<string, string> */
    public const JENIS_KELAMIN = [
        'laki-laki' => 'Laki-laki',
        'perempuan' => 'Perempuan',
    ];

    /** @var array<string, string> */
    public const AGAMA = [
        'Islam' => 'Islam',
        'Kristen' => 'Kristen',
        'Katolik' => 'Katolik',
        'Hindu' => 'Hindu',
        'Buddha' => 'Buddha',
        'Konghucu' => 'Konghucu',
    ];

    /** @var array<string, string> */
    public const DOKUMEN = [
        'foto' => 'Foto calon murid',
        'kk' => 'Kartu Keluarga (KK)',
        'ijazah' => 'Ijazah',
        'kip' => 'KIP (opsional)',
    ];

    /**
     * Data yang wajib diisi calon siswa setelah masuk ke akunnya.
     *
     * @var array<string, string>
     */
    public const DATA_WAJIB = [
        'nik' => 'NIK',
        'no_kk' => 'Nomor Kartu Keluarga',
        'nisn' => 'NISN',
        'tempat_lahir' => 'Tempat lahir',
        'tanggal_lahir' => 'Tanggal lahir',
        'agama' => 'Agama',
        'alamat' => 'Alamat lengkap',
        'nama_ayah' => 'Nama ayah',
        'status_ayah' => 'Status ayah',
        'nama_ibu' => 'Nama ibu',
        'status_ibu' => 'Status ibu',
        'kk' => 'Dokumen Kartu Keluarga',
        'ijazah' => 'Dokumen ijazah',
    ];

    protected $fillable = [
        'user_id',
        'nomor_pendaftaran',
        'nama',
        'jenis_kelamin',
        'email',
        'nohp',
        'nik',
        'no_kk',
        'nisn',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'alamat',
        'nohp_siswa',
        'nama_ayah',
        'status_ayah',
        'nik_ayah',
        'pekerjaan_ayah',
        'penghasilan_ayah',
        'nohp_ayah',
        'nama_ibu',
        'status_ibu',
        'nik_ibu',
        'pekerjaan_ibu',
        'penghasilan_ibu',
        'nohp_ibu',
        'foto',
        'ijazah',
        'kk',
        'kip',
        'nilai_tes',
        'status',
        'catatan',
        'murid_id',
        'diterima_pada',
        'dilengkapi_pada',
    ];

    protected $hidden = [
        'login_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'nilai_tes' => 'integer',
            'tanggal_lahir' => 'date',
            'diterima_pada' => 'datetime',
            'dilengkapi_pada' => 'datetime',
            'login_token_expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $calon): void {
            $calon->nomor_pendaftaran ??= 'PPDB-'.now()->format('Y').'-'.strtoupper(Str::random(6));
        });

        // Tandai kelengkapan data setiap kali calon siswa disimpan, baik dari akun
        // calon siswa maupun saat admin melengkapi data lewat panel.
        static::saving(function (self $calon): void {
            $calon->dilengkapi_pada = $calon->dataLengkap()
                ? ($calon->dilengkapi_pada ?? now())
                : null;
        });

        static::updated(function (self $calon): void {
            if ($calon->wasChanged('nama')) {
                $calon->user?->update(['name' => $calon->nama]);
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Murid, $this> */
    public function murid(): BelongsTo
    {
        return $this->belongsTo(Murid::class);
    }

    public function sudahDiterima(): bool
    {
        return $this->murid_id !== null
            || $this->status === self::STATUS_DITERIMA;
    }

    /** @return list<string> */
    public function dataBelumLengkap(): array
    {
        return collect(self::DATA_WAJIB)
            ->filter(fn (string $label, string $field): bool => blank($this->{$field}))
            ->values()
            ->all();
    }

    public function dataLengkap(): bool
    {
        return $this->dataBelumLengkap() === [];
    }

    /**
     * Data administrasi calon siswa yang ikut dipindahkan saat dikonversi
     * menjadi data siswa.
     *
     * @return array<string, mixed>
     */
    public function atributSiswa(): array
    {
        return [
            'nama' => $this->nama,
            'jenis_kelamin' => $this->jenis_kelamin,
            'email' => $this->email,
            'nisn' => $this->nisn,
            'nohp_siswa' => $this->nohp_siswa ?? $this->nohp,
            'nik' => $this->nik,
            'no_kk' => $this->no_kk,
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir,
            'agama' => $this->agama,
            'alamat' => $this->alamat,
            'nama_ayah' => $this->nama_ayah,
            'status_ayah' => $this->status_ayah,
            'nik_ayah' => $this->nik_ayah,
            'pekerjaan_ayah' => $this->pekerjaan_ayah,
            'penghasilan_ayah' => $this->penghasilan_ayah,
            'nohp_ayah' => $this->nohp_ayah,
            'nama_ibu' => $this->nama_ibu,
            'status_ibu' => $this->status_ibu,
            'nik_ibu' => $this->nik_ibu,
            'pekerjaan_ibu' => $this->pekerjaan_ibu,
            'penghasilan_ibu' => $this->penghasilan_ibu,
            'nohp_ibu' => $this->nohp_ibu,
            'foto' => $this->foto,
            'ijazah' => $this->ijazah,
            'kk' => $this->kk,
            'kip' => $this->kip,
        ];
    }

    /**
     * Buat tautan masuk sekali pakai untuk email pendaftaran.
     *
     * Token asli hanya dikirim lewat email; database menyimpan hash-nya.
     */
    public function buatTautanMasuk(): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'login_token' => hash('sha256', $token),
            'login_token_expires_at' => now()->addDays(self::TAUTAN_MASUK_HARI),
        ])->save();

        return $token;
    }

    public static function cariLewatTautanMasuk(string $token): ?self
    {
        return static::query()
            ->where('login_token', hash('sha256', $token))
            ->where('login_token_expires_at', '>', now())
            ->first();
    }

    public function hapusTautanMasuk(): void
    {
        $this->forceFill([
            'login_token' => null,
            'login_token_expires_at' => null,
        ])->save();
    }

    /** @return array<string, string> */
    public static function statusColors(): array
    {
        return [
            self::STATUS_MENUNGGU => 'warning',
            self::STATUS_LULUS => 'success',
            self::STATUS_TIDAK_LULUS => 'danger',
            self::STATUS_DITERIMA => 'info',
        ];
    }
}
