#!/usr/bin/env bash
set -Eeuo pipefail

SESI="${1:-1}"
REPO="$(git rev-parse --show-toplevel)"
cd "$REPO"

echo "=== Rekap Sesi ${SESI} ==="
echo "Repository: $REPO"
echo "Branch: $(git branch --show-current)"
echo "Commit: $(git log -1 --format='%h %s')"

echo ""
echo "=== Riwayat Merge ==="
git log --oneline --merges -10

echo ""
echo "=== Laravel Tests ==="
docker compose exec -T app php artisan test

echo ""
echo "=== Laravel Pint ==="
docker compose exec -T app ./vendor/bin/pint --test

echo ""
echo "=== Pemeriksaan Buku Besar ==="
docker compose exec -T app php artisan lapak:periksa-buku

echo ""
echo "=== Rute API v1 ==="
docker compose exec -T app php artisan route:list --path=api/v1

echo ""
echo "=== Rekap selesai ==="
