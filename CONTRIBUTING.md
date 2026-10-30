# Kontribusi — Lapak API

## Aturan Cabang
- Cabang utama: `main` (terlindungi, hanya integrator yang bisa merge).
- Cabang fondasi: `sesi-1/fondasi` (dipakai bersama saat Tahap 1).
- Cabang fitur: `sesi-1/<jenis>-<domain>-<nama>` (milik pribadi).

## Jadwal Unggahan
| Tahap | Tenggat |
|-------|---------|
| Fondasi | Hari pertemuan (H) |
| PR Kontrak (Jendela A) | Hari pertemuan (H) |
| PR Implementasi draf (Jendela B) | H+2 |
| PR Implementasi siap tinjau | H+4 |
| Merge Jendela B | H+5 |

## Cara Push ke Cabang Bersama
Jika `git push` ditolak, gunakan:
git pull --rebase origin <nama-cabang>
git push origin <nama-cabang>

**Dilarang** menggunakan `git push --force` pada cabang bersama.

## Penamaan Migration
Pola: `2026_10_01_DNNNNN_nama.php`
- D = nomor domain (1-5)
- NNNNN = nomor urut (00001 dst)

Contoh: `2026_10_01_400001_buat_tabel_dompet.php`
