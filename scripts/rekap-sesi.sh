#!/usr/bin/env bash
# Rekap Sesi 1 — Lapak Marketplace
# Penggunaan: ./scripts/rekap-sesi.sh <nomor-sesi>

set -euo pipefail

SESI="${1:-1}"
REPO="$(git rev-parse --show-toplevel)"

echo "=== Rekap Sesi ${SESI} ==="
echo "Repo: ${REPO}"
echo "Branch: $(git branch --show-current)"
echo "Commit terakhir: $(git log -1 --format='%h %s')"
echo ""

echo "--- Daftar PR yang sudah merge ---"
git log --oneline --grep="Merge pull request" | head -20 || true
echo ""

echo "--- Status tes ---"
if [ -x "./vendor/bin/pest" ]; then
    ./vendor/bin/pest --no-coverage || true
else
    echo "Pest tidak ditemukan."
fi
echo ""

echo "--- Cek buku besar ---"
php artisan lapak:periksa-buku || true
echo ""

echo "--- Daftar rute API ---"
php artisan route:list --path=api/v1 || true
