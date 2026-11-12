<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use InvalidArgumentException;

final class RantaiHash
{
    public static function awal(): string
    {
        return str_repeat('0', 64);
    }

    public static function hitung(string $sebelumnya, array $isi): string
    {
        return hash('sha256', $sebelumnya."\n".self::kanonik($isi));
    }

    public static function kanonik(array $isi): string
    {
        return json_encode(
            self::urutkan($isi),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    private static function urutkan(mixed $nilai): mixed
    {
        if (is_float($nilai)) {
            throw new InvalidArgumentException('Isi log audit tidak boleh memuat pecahan.');
        }

        if (is_object($nilai)) {
            throw new InvalidArgumentException('Isi log audit harus berupa skalar atau larik.');
        }

        if (! is_array($nilai)) {
            return $nilai;
        }

        if (! array_is_list($nilai)) {
            ksort($nilai, SORT_STRING);
        }

        return array_map(self::urutkan(...), $nilai);
    }
}
