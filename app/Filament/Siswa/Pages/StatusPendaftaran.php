<?php

namespace App\Filament\Siswa\Pages;

use App\Models\CalonSiswa;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class StatusPendaftaran extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Status Pendaftaran';

    protected static ?string $title = 'Status Pendaftaran';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'status';

    protected string $view = 'filament.siswa.pages.status-pendaftaran';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pantau hasil seleksi dan kelengkapan data pendaftaran Anda.';
    }

    public function calon(): CalonSiswa
    {
        $calon = Filament::auth()->user()
            ?->calonSiswa()
            ->with(['murid.kelas', 'murid.jenisPeminatans'])
            ->first();

        abort_if($calon === null, 403);

        return $calon;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'calon' => $this->calon(),
        ];
    }
}
