<?php

namespace Database\Factories;

use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiteSetting> */
class SiteSettingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return (new SiteSetting)->attributesToArray();
    }
}
