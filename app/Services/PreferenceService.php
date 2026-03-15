<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Preference;

final class PreferenceService
{
    public static function seedDefaults(): void
    {
        Preference::setIfMissing('articles_per_page', '10');
    }
}
