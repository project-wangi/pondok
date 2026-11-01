<?php

namespace App\Filament\Siswa\Pages;

use App\Models\CalonSiswa;
use App\Models\Murid;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class DataPendaftaran extends Page
{
    /** Filter input agar kolom angka tidak bisa diisi teks. */
    private const FILTER_ANGKA = 'this.value = this.value.replace(/\D/g, "")';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Data Pendaftaran';

    protected static ?string $title = 'Data Administrasi Pendaftaran';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'data-pendaftaran';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function getSubheading(): string|Htmlable|null
    {
        return 'Lengkapi data administrasi dan unggah dokumen. Pendaftaran dinyatakan lengkap setelah semua bagian wajib terisi.';
    }

    public function mount(): void
    {
        $calon = $this->calon();

        $this->form->fill([
            'nik' => $calon->nik,
            'no_kk' => $calon->no_kk,
            'nisn' => $calon->nisn,
            'tempat_lahir' => $calon->tempat_lahir,
            'tanggal_lahir' => $calon->tanggal_lahir?->toDateString(),
            'agama' => $calon->agama,
            'nohp_siswa' => $calon->nohp_siswa,
            'alamat' => $calon->alamat,
            'nama_ayah' => $calon->nama_ayah,
            'status_ayah' => $calon->status_ayah,
            'nik_ayah' => $calon->nik_ayah,
            'pekerjaan_ayah' => $calon->pekerjaan_ayah,
            'penghasilan_ayah' => $calon->penghasilan_ayah,
            'nohp_ayah' => $calon->nohp_ayah,
            'nama_ibu' => $calon->nama_ibu,
            'status_ibu' => $calon->status_ibu,
            'nik_ibu' => $calon->nik_ibu,
            'pekerjaan_ibu' => $calon->pekerjaan_ibu,
            'penghasilan_ibu' => $calon->penghasilan_ibu,
            'nohp_ibu' => $calon->nohp_ibu,
            'foto' => $calon->foto,
            'kk' => $calon->kk,
            'ijazah' => $calon->ijazah,
            'kip' => $calon->kip,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Data Pribadi')
                    ->schema([
                        TextInput::make('nik')
                            ->label('NIK')
                            ->required()
                            ->maxLength(16)
                            ->inputMode('numeric')
                            ->extraInputAttributes(['oninput' => self::FILTER_ANGKA])
                            ->rule('digits:16')
                            ->helperText('16 angka sesuai KTP/KK calon murid.'),
                        TextInput::make('no_kk')
                            ->label('Nomor Kartu Keluarga')
                            ->required()
                            ->maxLength(16)
                            ->inputMode('numeric')
                            ->extraInputAttributes(['oninput' => self::FILTER_ANGKA])
                            ->rule('digits:16'),
                        TextInput::make('nisn')
                            ->label('NISN')
                            ->required()
                            ->maxLength(10)
                            ->inputMode('numeric')
                            ->extraInputAttributes(['oninput' => self::FILTER_ANGKA])
                            ->rule('digits:10')
                            ->rule(fn (): Unique => Rule::unique('calon_siswas', 'nisn')->ignore($this->calon()->id))
                            ->helperText('10 angka sesuai Nomor Induk Siswa Nasional. NIS diisi sekolah saat Anda dinyatakan diterima.'),
                        TextInput::make('tempat_lahir')
                            ->label('Tempat Lahir')
                            ->required()
                            ->maxLength(100),
                        DatePicker::make('tanggal_lahir')
                            ->label('Tanggal Lahir')
                            ->required()
                            ->maxDate(now()),
                        Select::make('agama')
                            ->label('Agama')
                            ->options(CalonSiswa::AGAMA)
                            ->required(),
                        TextInput::make('nohp_siswa')
                            ->label('Nomor HP Calon Murid')
                            ->tel()
                            ->inputMode('numeric')
                            ->extraInputAttributes(['oninput' => self::FILTER_ANGKA])
                            ->maxLength(20)
                            ->helperText('Opsional. Diawali angka 0, tanpa spasi atau strip.'),
                        Textarea::make('alamat')
                            ->label('Alamat Lengkap')
                            ->required()
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Data Ayah')
                    ->schema([
                        TextInput::make('nama_ayah')
                            ->label('Nama Ayah')
                            ->required()
                            ->maxLength(150),
                        Select::make('status_ayah')
                            ->label('Status Ayah')
                            ->options(Murid::STATUS_ORANG_TUA)
                            ->default('Hidup')
                            ->required(),
                        TextInput::make('nik_ayah')
                            ->label('NIK Ayah')
                            ->maxLength(16)
                            ->inputMode('numeric')
                            ->extraInputAttributes(['oninput' => self::FILTER_ANGKA])
                            ->rule('digits:16')
                            ->helperText('Opsional.'),
                        TextInput::make('pekerjaan_ayah')
                            ->label('Pekerjaan Ayah')
                            ->maxLength(100)
                            ->helperText('Opsional.'),
                        TextInput::make('penghasilan_ayah')
                            ->label('Penghasilan Ayah')
                            ->maxLength(50)
                            ->placeholder('Contoh: Rp2.000.000')
                            ->helperText('Opsional.'),
                        TextInput::make('nohp_ayah')
                            ->label('Nomor HP Ayah')
                            ->tel()
                            ->inputMode('numeric')
                            ->extraInputAttributes(['oninput' => self::FILTER_ANGKA])
                            ->maxLength(20)
                            ->helperText('Opsional.'),
                    ])->columns(2),

                Section::make('Data Ibu')
                    ->schema([
                        TextInput::make('nama_ibu')
                            ->label('Nama Ibu')
                            ->required()
                            ->maxLength(150),
                        Select::make('status_ibu')
                            ->label('Status Ibu')
                            ->options(Murid::STATUS_ORANG_TUA)
                            ->default('Hidup')
                            ->required(),
                        TextInput::make('nik_ibu')
                            ->label('NIK Ibu')
                            ->maxLength(16)
                            ->inputMode('numeric')
                            ->extraInputAttributes(['oninput' => self::FILTER_ANGKA])
                            ->rule('digits:16')
                            ->helperText('Opsional.'),
                        TextInput::make('pekerjaan_ibu')
                            ->label('Pekerjaan Ibu')
                            ->maxLength(100)
                            ->helperText('Opsional.'),
                        TextInput::make('penghasilan_ibu')
                            ->label('Penghasilan Ibu')
                            ->maxLength(50)
                            ->placeholder('Contoh: Rp1.500.000')
                            ->helperText('Opsional.'),
                        TextInput::make('nohp_ibu')
                            ->label('Nomor HP Ibu')
                            ->tel()
                            ->inputMode('numeric')
                            ->extraInputAttributes(['oninput' => self::FILTER_ANGKA])
                            ->maxLength(20)
                            ->helperText('Opsional.'),
                    ])->columns(2),

                Section::make('Dokumen Pendaftaran')
                    ->description('Format PDF, JPG, atau PNG. Ukuran maksimal 2 MB per berkas.')
                    ->schema([
                        FileUpload::make('foto')
                            ->label('Foto Calon Murid')
                            ->image()
                            ->maxSize(2048)
                            ->disk('r2')
                            ->visibility('private')
                            ->openable()
                            ->directory('calon-dokumen/foto')
                            ->deleteUploadedFileUsing(fn (string $file) => Storage::disk('r2')->delete($file))
                            ->helperText('Opsional. Pas foto berwarna, tampak depan.'),
                        FileUpload::make('kk')
                            ->label('Kartu Keluarga (KK)')
                            ->maxSize(2048)
                            ->disk('r2')
                            ->visibility('private')
                            ->openable()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->directory('calon-dokumen/kk')
                            ->deleteUploadedFileUsing(fn (string $file) => Storage::disk('r2')->delete($file))
                            ->required(fn (): bool => blank($this->calon()->kk)),
                        FileUpload::make('ijazah')
                            ->label('Ijazah')
                            ->maxSize(2048)
                            ->disk('r2')
                            ->visibility('private')
                            ->openable()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->directory('calon-dokumen/ijazah')
                            ->deleteUploadedFileUsing(fn (string $file) => Storage::disk('r2')->delete($file))
                            ->required(fn (): bool => blank($this->calon()->ijazah)),
                        FileUpload::make('kip')
                            ->label('KIP (opsional)')
                            ->maxSize(2048)
                            ->disk('r2')
                            ->visibility('private')
                            ->openable()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->directory('calon-dokumen/kip')
                            ->deleteUploadedFileUsing(fn (string $file) => Storage::disk('r2')->delete($file)),
                    ])->columns(2),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Simpan Data Pendaftaran')
                        ->submit('save'),
                ])->alignment(Alignment::End),
            ]);
    }

    public function save(): void
    {
        $calon = $this->calon();
        $data = $this->form->getState();

        $berkasLama = [];

        foreach (array_keys(CalonSiswa::DOKUMEN) as $field) {
            $baru = $data[$field] ?? null;

            if (filled($calon->{$field}) && filled($baru) && $baru !== $calon->{$field}) {
                $berkasLama[] = $calon->{$field};
            }
        }

        $calon->update($data);

        foreach ($berkasLama as $path) {
            Storage::disk('r2')->delete($path);
        }

        Notification::make()
            ->success()
            ->title($calon->dataLengkap() ? 'Data pendaftaran sudah lengkap' : 'Data tersimpan')
            ->body($calon->dataLengkap()
                ? 'Pendaftaran Anda tercatat sebagai pendaftar lengkap.'
                : 'Masih ada bagian wajib yang belum terisi: '.implode(', ', $calon->dataBelumLengkap()).'.')
            ->send();

        $this->redirect(StatusPendaftaran::getUrl(panel: 'siswa'));
    }

    public function calon(): CalonSiswa
    {
        $calon = Filament::auth()->user()?->calonSiswa()->first();

        abort_if($calon === null, 403);

        return $calon;
    }
}
