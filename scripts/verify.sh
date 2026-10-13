#!/usr/bin/env bash
#
# Satu perintah untuk menjalankan SELURUH verifikasi proyek:
#   1. Feature test backend (Pest)
#   2. E2E Playwright
#   3. Build aset frontend (Vite)
#   4. Uji race stok motif (2 checkout paralel lewat HTTP sungguhan)
#
# Kenapa test backend harus jalan di dalam container:
#   Dependency dev Pest (brianium/paratest) mensyaratkan PHP ~8.2–8.4, sedangkan
#   PHP di mesin ini 8.5.7 — jadi `composer install` di host GAGAL (bukan karena
#   kodenya rusak). Container memakai php:8.2-fpm-alpine sesuai Dockerfile.
#
# Jalankan:  bash scripts/verify.sh
#
set -uo pipefail
cd "$(dirname "$0")/.."

COMPOSE=(-f docker-compose.yml -f docker-compose.e2e.yml)
FAILED=()

run_step() {
  local nama="$1"; shift
  echo ""
  echo "======================================================================"
  echo "==> ${nama}"
  echo "======================================================================"

  if "$@"; then
    echo "--- ${nama}: OK"
  else
    echo "--- ${nama}: GAGAL"
    FAILED+=("${nama}")
  fi
}

step_pest() { docker compose "${COMPOSE[@]}" exec -T app php artisan test; }
step_build() { docker compose "${COMPOSE[@]}" exec -T app npm run build; }
step_playwright() { npx playwright test; }
step_race() { bash scripts/race-variant-stock.sh; }

echo "Stack: $(curl -sS -o /dev/null -w '%{http_code}' "${E2E_BASE_URL:-http://localhost:8000}/up" || echo 'tidak merespons')"

run_step "Feature test backend (Pest)" step_pest
run_step "Build aset frontend (Vite)" step_build
run_step "E2E Playwright" step_playwright
run_step "Uji race stok motif" step_race

echo ""
echo "======================================================================"
if [ ${#FAILED[@]} -eq 0 ]; then
  echo "RINGKASAN: semua verifikasi hijau."
  exit 0
fi

echo "RINGKASAN: gagal -> ${FAILED[*]}"
exit 1
