<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RecoveryCode;

final class RecoveryCodeService
{
    public function regenerate(int $userId): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2)));
        }
        RecoveryCode::replaceForUser($userId, $codes);
        return $codes;
    }
}
