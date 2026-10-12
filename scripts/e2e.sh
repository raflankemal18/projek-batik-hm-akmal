#!/usr/bin/env bash
#
# Menjalankan stack Docker khusus E2E lalu suite Playwright.
#
#   bash scripts/e2e.sh              # seluruh spec
#   bash scripts/e2e.sh auth.spec    # subset spec
#
# Env:
#   E2E_BASE_URL   override base URL (default http://localhost:8000)
#   SKIP_BUILD=1   lewati `docker compose build` (pakai image lama)
#
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE_FILES=(-f docker-compose.yml -f docker-compose.e2e.yml)
BASE_URL="${E2E_BASE_URL:-http://localhost:8000}"

echo "==> Menyalakan stack E2E (marketplace_batik_e2e)"
if [ "${SKIP_BUILD:-0}" = "1" ]; then
  docker compose "${COMPOSE_FILES[@]}" up -d
else
  docker compose "${COMPOSE_FILES[@]}" up -d --build
fi

echo "==> Menunggu aplikasi siap di ${BASE_URL}"
for i in $(seq 1 90); do
  if curl -fsS "${BASE_URL}/up" >/dev/null 2>&1; then
    echo "    siap setelah ${i}s"
    break
  fi
  if [ "${i}" = "90" ]; then
    echo "    GAGAL: ${BASE_URL}/up tidak merespons dalam 90s."
    echo "    Lihat log: docker compose ${COMPOSE_FILES[*]} logs app nginx"
    exit 1
  fi
  sleep 1
done

echo "==> Build aset frontend (penting setelah JSX/testid berubah)"
docker compose "${COMPOSE_FILES[@]}" exec -T app npm run build

echo "==> Menjalankan Playwright"
E2E_BASE_URL="${BASE_URL}" npx playwright test "$@"