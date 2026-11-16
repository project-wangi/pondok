<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/**
 * Picker jam 24 jam dengan kolom jam dan menit.
 *
 * Nilai disimpan sebagai HH:MM:00 agar konsisten dengan kolom time pada
 * database, tetapi ditampilkan dan dipilih sebagai HH:MM.
 */
class JamPicker extends Field
{
    protected string $view = 'filament.forms.components.jam-picker';

    protected int $minutesStep = 5;

    /**
     * Jarak antar opsi menit, misalnya 5 untuk 00, 05, 10, ...
     */
    public function minutesStep(int $step): static
    {
        $this->minutesStep = max(1, min(60, $step));

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (JamPicker $component, mixed $state): void {
            if (is_string($state) && strlen($state) > 5) {
                $component->state(substr($state, 0, 5));
            }
        });

        $this->dehydrateStateUsing(function (mixed $state): ?string {
            if (blank($state)) {
                return null;
            }

            return substr((string) $state, 0, 5).':00';
        });
    }

    /** @return list<string> */
    public function getHours(): array
    {
        $hours = [];

        for ($hour = 0; $hour <= 23; $hour++) {
            $hours[] = sprintf('%02d', $hour);
        }

        return $hours;
    }

    /** @return list<string> */
    public function getMinutes(): array
    {
        $minutes = [];

        for ($minute = 0; $minute < 60; $minute += $this->minutesStep) {
            $minutes[] = sprintf('%02d', $minute);
        }

        return $minutes;
    }
}
