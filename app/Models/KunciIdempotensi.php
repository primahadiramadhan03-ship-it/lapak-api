<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan kunci idempotensi untuk menjamin mutasi krusial tidak dieksekusi ganda (LP-11).
 *
 * @property int $id
 * @property int|null $pengguna_id
 * @property string $kunci
 * @property string $metode
 * @property string $jalur
 * @property string $hash_permintaan
 * @property int|null $status_kode
 * @property array<string, mixed>|null $respons
 */
final class KunciIdempotensi extends Model
{
    use HasFactory;

    protected $table = 'kunci_idempotensi';

    protected $fillable = [
        'pengguna_id',
        'kunci',
        'metode',
        'jalur',
        'hash_permintaan',
        'status_kode',
        'respons',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_kode' => 'integer',
            'respons' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Pengguna, $this>
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }

    /**
     * Menghasilkan hash SHA-256 deterministik dari payload permintaan.
     */
    public static function hitungHash(mixed $payload): string
    {
        if (is_array($payload)) {
            self::urutkanArrayRekursif($payload);
            $payload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return hash('sha256', (string) $payload);
    }

    /**
     * Mengurutkan kunci array secara rekursif agar hash selalu konsisten.
     *
     * @param  array<string, mixed>  $array
     */
    private static function urutkanArrayRekursif(array &$array): void
    {
        ksort($array);
        foreach ($array as &$nilai) {
            if (is_array($nilai)) {
                self::urutkanArrayRekursif($nilai);
            }
        }
    }
}
