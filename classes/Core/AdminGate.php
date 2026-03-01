<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

class AdminGate
{
    public static function canAccess(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ((int)$user->getId() === 1) {
            return true;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $email = strtolower(trim((string)$user->getEmail()));
        if ($email === '') {
            return false;
        }

        $allowedCsv = strtolower(trim((string)($_ENV['ADMIN_EMAILS'] ?? $_ENV['ADMIN_EMAIL'] ?? '')));
        if ($allowedCsv === '') {
            return false;
        }

        $allowed = array_filter(array_map(static fn(string $v): string => trim($v), explode(',', $allowedCsv)));
        return in_array($email, $allowed, true);
    }
}
