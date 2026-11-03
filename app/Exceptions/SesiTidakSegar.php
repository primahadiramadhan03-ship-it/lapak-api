<?php

declare(strict_types=1);

namespace App\Exceptions;

final class SesiTidakSegar extends GalatDomain
{
    public static function buat(): self
    {
        return new self('LP-15', 'Sesi tidak segar. Kata sandi harus dikonfirmasi ulang.', 403);
    }
}
