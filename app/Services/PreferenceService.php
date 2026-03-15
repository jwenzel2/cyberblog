<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Preference;

final class PreferenceService
{
    public static function seedDefaults(): void
    {
        Preference::setIfMissing('articles_per_page', '10');
        Preference::setIfMissing('smtp_enabled', '0');
        Preference::setIfMissing('smtp_host', '');
        Preference::setIfMissing('smtp_port', '587');
        Preference::setIfMissing('smtp_username', '');
        Preference::setIfMissing('smtp_password', '');
        Preference::setIfMissing('smtp_encryption', 'tls');
        Preference::setIfMissing('smtp_from_email', '');
        Preference::setIfMissing('smtp_from_name', 'CyberBlog');
    }
}
