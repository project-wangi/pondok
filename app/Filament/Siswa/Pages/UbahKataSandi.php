<?php

namespace App\Filament\Siswa\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
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

class UbahKataSandi extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Kata Sandi';

    protected static ?string $title = 'Ubah Kata Sandi';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'kata-sandi';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function getSubheading(): string|Htmlable|null
    {
        return 'Gunakan kata sandi yang mudah Anda ingat, minimal 8 karakter.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('password')
                            ->label('Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8),
                        TextInput::make('password_confirmation')
                            ->label('Ulangi Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8),
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
                        ->label('Simpan Kata Sandi')
                        ->submit('save'),
                ])->alignment(Alignment::End),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if ($data['password'] !== $data['password_confirmation']) {
            Notification::make()
                ->danger()
                ->title('Konfirmasi kata sandi belum sama.')
                ->send();

            return;
        }

        $user = Filament::auth()->user();

        abort_if($user === null, 403);

        $user->update(['password' => $data['password']]);

        $this->form->fill();

        Notification::make()
            ->success()
            ->title('Kata sandi berhasil diperbarui.')
            ->body('Gunakan kata sandi baru saat masuk berikutnya.')
            ->send();
    }
}
