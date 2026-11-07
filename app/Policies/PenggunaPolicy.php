<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pengguna;

final class PenggunaPolicy
{
    /**
     * Tidak seorang pun boleh mengubah perannya sendiri.
     */
    public function ubahPeran(Pengguna $pelaku, Pengguna $sasaran): bool
    {
        return ! $pelaku->is($sasaran);
    }
}
