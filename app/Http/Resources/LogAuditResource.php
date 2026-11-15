<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Audit\RantaiHash;
use App\Models\LogAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu baris log audit seperti yang dilihat auditor (LP-19).
 *
 * Seluruh isinya memang boleh dibaca pemegang izin audit.lihat. Rahasia tidak pernah masuk ke
 * tabel ini: `PencatatAudit` menolak kunci terlarang sebelum baris ditulis.
 *
 * `tertaut` dihitung ulang saat dibaca, sehingga auditor tidak perlu memercayai angka yang
 * tersimpan: bila isi baris diubah langsung di basis data, `tertaut` menjadi false.
 *
 * @mixin LogAudit
 */
final class LogAuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'peristiwa' => $this->peristiwa,
            'pelaku' => $this->whenLoaded('pelaku', fn (): ?array => $this->pelaku === null ? null : [
                'id' => $this->pelaku->id,
                'nama' => $this->pelaku->nama,
            ]),
            'sasaran' => $this->sasaran,
            'rincian' => $this->rincian,
            'id_permintaan' => $this->id_permintaan,
            'dicatat_pada' => $this->dicatat_pada?->toIso8601String(),
            'hash_sebelumnya' => $this->hash_sebelumnya,
            'hash' => $this->hash,
            'tertaut' => hash_equals(
                $this->hash,
                RantaiHash::hitung($this->hash_sebelumnya, $this->isiTerlindung()),
            ),
        ];
    }
}
