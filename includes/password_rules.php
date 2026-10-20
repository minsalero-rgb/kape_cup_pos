<?php
declare(strict_types=1);

function validate_new_password(string $password): ?string
{
    $characters = preg_match_all('/./us', $password);
    if ($characters === false) {
        return 'The password contains invalid characters.';
    }
    if ($characters < 12) {
        return 'Password must be at least 12 characters.';
    }
    if (strlen($password) > 72) {
        return 'Password must be no more than 72 bytes (long or special-character passwords count extra).';
    }
    return null;
}
