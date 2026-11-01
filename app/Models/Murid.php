<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Murid extends Model
{
    public const STATUS_AKTIF = 'Aktif';

    public const STATUS_LULUS = 'Lulus';

    public const STATUS_KELUAR = 'Keluar';

    /** @var array<string, string> */
    public const STATUS = [
        self::STATUS_AKTIF => 'Aktif',
        self::STATUS_LULUS => 'Lulus',
        self::STATUS_KELUAR => 'Keluar',
    ];

    /** @var array<string, string> */
    public const STATUS_ORANG_TUA = [
        'Hidup' => 'Hidup',
        'Meninggal' => 'Meninggal',
    ];

    protected $fillable = [
        'kelas_id',
        'status',
        'tanggal_lulus',
        'tanggal_keluar',
        'foto',
        'nama',
        'nisn',
        'nis',
        'nik',
        'no_kk',
        'email',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'alamat',
        'nama_ayah',
        'status_ayah',
        'nik_ayah',
        'pekerjaan_ayah',
        'nama_ibu',
        'status_ibu',
        'nik_ibu',
        'pekerjaan_ibu',
        'penghasilan_ayah',
        'penghasilan_ibu',
        'nohp_siswa',
        'nohp_ayah',
        'nohp_ibu',
        'ijazah',
        'kk',
        'kip',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tanggal_lulus' => 'date',
            'tanggal_keluar' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Tanggal lulus/keluar diisi otomatis sesuai status terbaru.
        static::saving(function (self $murid): void {
            if ($murid->status === self::STATUS_LULUS) {
                $murid->tanggal_lulus ??= now()->toDateString();
                $murid->tanggal_keluar = null;

                return;
            }

            if ($murid->status === self::STATUS_KELUAR) {
                $murid->tanggal_keluar ??= now()->toDateString();
                $murid->tanggal_lulus = null;

                return;
            }

            $murid->tanggal_lulus = null;
            $murid->tanggal_keluar = null;
        });
    }

    /** @param Builder<self> $query */
    public function scopeAktif(Builder $query): void
    {
        $query->where('status', self::STATUS_AKTIF);
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    /** @return array<string, string> */
    public static function statusColors(): array
    {
        return [
            self::STATUS_AKTIF => 'success',
            self::STATUS_LULUS => 'info',
            self::STATUS_KELUAR => 'danger',
        ];
    }

    /** @return BelongsToMany<JenisPeminatan, $this> */
    public function jenisPeminatans(): BelongsToMany
    {
        return $this->belongsToMany(JenisPeminatan::class, 'jenis_peminatan_murid');
    }

    public function jenisKelaminKode(): string
    {
        return match (mb_strtolower(trim((string) $this->jenis_kelamin))) {
            'laki-laki', 'laki laki', 'l', 'lk' => 'L',
            'perempuan', 'p', 'pr' => 'P',
            default => '-',
        };
    }
}
