<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->string('unit', 10)->nullable();
        });

        $this->isiUnitDariNamaKelas();
    }

    /**
     * Unit lama masih diturunkan dari nama kelas, jadi diisi sekali di sini
     * agar data yang sudah ada langsung memiliki unit tersimpan.
     */
    private function isiUnitDariNamaKelas(): void
    {
        $romawi = [
            'VII' => 'MTs',
            'VIII' => 'MTs',
            'IX' => 'MTs',
            'X' => 'MA',
            'XI' => 'MA',
            'XII' => 'MA',
        ];

        foreach (DB::table('kelas')->orderBy('id')->get(['id', 'nama_kelas']) as $kelas) {
            $nama = mb_strtoupper(trim((string) $kelas->nama_kelas));
            $unit = null;

            foreach (preg_split('/[\s\-_.\/]+/', $nama) ?: [] as $token) {
                if (isset($romawi[$token])) {
                    $unit = $romawi[$token];

                    break;
                }

                if (ctype_digit($token) && (int) $token >= 1 && (int) $token <= 12) {
                    $unit = (int) $token <= 9 ? 'MTs' : 'MA';

                    break;
                }
            }

            if ($unit !== null) {
                DB::table('kelas')->where('id', $kelas->id)->update(['unit' => $unit]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
