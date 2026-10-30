# TIM — Lapak Marketplace

## Susunan Tim

| Nama di Tim | Nama di Modul | Domain | Peran Sesi 1 |
|---|---|---|---|
| Prima Hadi Ramadhan | Bambang | D1 — Identitas & Sesi | Integrator |
| Syafiq Syadi | Bagas | D2 — Toko & Katalog | Anggota |
| Briliant | Rani | D3 — Keranjang & Pesanan | Anggota |
| Risa | Dimas | D4 — Pembayaran & Saldo | Anggota |
| Jaya | Sekar | D5 — Otorisasi, Audit & Kualitas | Penjaga berkas bersama dan CI |

## Integrator Bergilir

| Sesi | Fokus | Integrator |
|---|---|---|
| 1 | Fondasi tim dan repositori | Prima |
| 2 | Identitas dan session dengan kunci | Syafiq |
| 3 | RBAC (peran dan izin) | Briliant |
| 4 | Keamanan transaksi | Risa |
| 5 | Escrow, saldo, audit, dan rilis v1.0.0 | Jaya |

## Lingkar Review Sesi 1

- Prima (D1) → Syafiq
- Syafiq (D2) → Briliant
- Briliant (D3) → Risa
- Risa (D4) → Jaya
- Jaya (D5) → Prima

## Aturan Cabang

- `main` — dilindungi branch protection, hanya integrator yang bisa merge.
- `sesi-1/fondasi-*` — commit dokumen fondasi.
- `sesi-1/kontrak-*` — Jendela A.
- `sesi-1/*` — Jendela B, mengikuti jadwal dan ketentuan modul.
