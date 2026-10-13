#!/usr/bin/env bash
#
# Uji race stok motif secara konkuren (P8).
#
# Dua user nyata logged-in menembak POST /checkout pada PRODUK + MOTIF yang sama
# dengan stok 1, dua request dikirim PARALEL lewat HTTP sungguhan (bukan test
# Pest — Pest berjalan berurutan di bawah satu transaksi, jadi tidak pernah
# benar-benar menguji competition).
#
# Yang diperiksa:
#   1. Stok motif tidak pernah jadi negatif.
#   2. Hanya satu order yang terbentuk.
#   3. Pemenang diarahkan ke /pembayaran.
#   4. Yang kalah dapat 302 kembali (bukan 500 mentah) dengan pesan stok habis.
#
# Jalankan:  bash scripts/race-variant-stock.sh
#
set -uo pipefail
cd "$(dirname "$0")/.."

COMPOSE=(-f docker-compose.yml -f docker-compose.e2e.yml)
BASE_URL="${E2E_BASE_URL:-http://localhost:8000}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

artisan() { docker compose "${COMPOSE[@]}" exec -T app php artisan "$@"; }
db() { docker compose "${COMPOSE[@]}" exec -T db mysql -uroot -proot marketplace_batik_e2e -N -B -e "$1" 2>/dev/null; }

echo "==> Menyiapkan data uji (motif dengan stok 1, dua user dengan keranjang)"
artisan e2e:reset --force >/dev/null

PRODUCT_ID="$(db "SELECT id FROM products WHERE has_variants = 1 ORDER BY id LIMIT 1;")"
VARIANT_ID="$(db "SELECT v.id FROM product_variants v WHERE v.product_id = ${PRODUCT_ID} AND stock > 0 ORDER BY v.id LIMIT 1;")"
VARIANT_NAME="$(db "SELECT name FROM product_variants WHERE id = ${VARIANT_ID};")"

if [ -z "$PRODUCT_ID" ] || [ -z "$VARIANT_ID" ]; then
  echo "GAGAL: tidak ada produk bermotif. Jalankan 'bash scripts/e2e.sh' dulu untuk menyiapkan stack."
  exit 1
fi

# Set stok motif = 1 lalu dua user (shopper & payer) dibuat punya keranjang berisi motif itu.
db "UPDATE product_variants SET stock = 1 WHERE id = ${VARIANT_ID};" >/dev/null

for EMAIL in shopper@e2e.test bayar@e2e.test; do
  USER_ID="$(db "SELECT id FROM users WHERE email = '${EMAIL}';")"
  CART_ID="$(db "SELECT id FROM carts WHERE user_id = ${USER_ID};")"
  if [ -z "$CART_ID" ]; then
    CART_ID="$(db "INSERT INTO carts (user_id, created_at, updated_at) VALUES (${USER_ID}, NOW(), NOW()); SELECT LAST_INSERT_ID();")"
  fi
  db "DELETE FROM cart_details WHERE cart_id = ${CART_ID};" >/dev/null
  db "INSERT INTO cart_details (cart_id, product_id, product_variant_id, quantity, size, created_at, updated_at)
      VALUES (${CART_ID}, ${PRODUCT_ID}, ${VARIANT_ID}, 1, '${VARIANT_NAME}', NOW(), NOW());" >/dev/null
done

echo "    produk #${PRODUCT_ID} motif '${VARIANT_NAME}' (#${VARIANT_ID}) stok = 1"

xsrf_token() {
  grep XSRF-TOKEN "$1" | awk '{print $7}' \
    | python3 -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))'
}

do_login() {
  local email="$1" jar="$2"

  curl -sS -c "$jar" -b "$jar" "$BASE_URL/login" >/dev/null
  curl -sS -c "$jar" -b "$jar" -X POST "$BASE_URL/login" \
    -H "X-XSRF-TOKEN: $(xsrf_token "$jar")" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "email=${email}&password=password" -o /dev/null
}

# Sesi diverifikasi lewat /cart: kalau masih Guests, jawabannya redirect ke
# /login — tanpa cek ini, request "yang kalah" bisa terlihat sukses padahal
# sebenarnya tidak pernah terautentikasi.
is_authenticated() {
  local jar="$1" loc
  loc="$(curl -sS -b "$jar" -c "$jar" -o /dev/null -w '%{redirect_url}' "$BASE_URL/cart")"
  [ -z "$loc" ]
}

login_and_checkout() {
  local email="$1" jar="$2" out="$3" attempt

  for attempt in 1 2 3; do
    do_login "$email" "$jar"
    if is_authenticated "$jar"; then break; fi
    sleep 1
  done

  if ! is_authenticated "$jar"; then
    echo "GAGAL: sesi untuk ${email} tidak berhasil login setelah 3 percobaan" >&2
    echo "HTTP 599" > "$out"
    return 1
  fi

  curl -sS -c "$jar" -b "$jar" -X POST "$BASE_URL/checkout" \
    -H "X-XSRF-TOKEN: $(xsrf_token "$jar")" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "name=Uji+Race&email=${email}&phone=081234567890&alamat=Jl+Race+1" \
    -D "$out" -o /dev/null
}

echo "==> Menembak 2 checkout paralel"
login_and_checkout "shopper@e2e.test" "$TMP/a.jar" "$TMP/a.header" &
PID_A=$!
login_and_checkout "bayar@e2e.test" "$TMP/b.jar" "$TMP/b.header" &
PID_B=$!
wait $PID_A $PID_B

status_of() { awk 'NR==1 {print $2}' "$1"; }
location_of() { grep -i '^location:' "$1" | tr -d '\r' | awk '{print $2}'; }

A_STATUS="$(status_of "$TMP/a.header")"
B_STATUS="$(status_of "$TMP/b.header")"
A_LOC="$(location_of "$TMP/a.header")"
B_LOC="$(location_of "$TMP/b.header")"

echo "    A: HTTP ${A_STATUS} -> ${A_LOC:-(-)}"
echo "    B: HTTP ${B_STATUS} -> ${B_LOC:-(-)}"

STOCK_NOW="$(db "SELECT stock FROM product_variants WHERE id = ${VARIANT_ID};")"
ORDER_COUNT="$(db "SELECT COUNT(*) FROM orders WHERE status = 'draft';")"
WINNERS=0
for L in "$A_LOC" "$B_LOC"; do case "$L" in *pembayaran*) WINNERS=$((WINNERS+1));; esac; done

# Pesan ramah untuk yang kalah: cek flash error-nya masih muncul di /co.
LOSER_JAR=""
if [ "$A_LOC" != "${A_LOC%/pembayaran}" ]; then LOSER_JAR="$TMP/b.jar"; fi
if [ "$B_LOC" != "${B_LOC%/pembayaran}" ]; then LOSER_JAR="$TMP/a.jar"; fi
LOSER_MSG="(tidak diperiksa)"
cat > "$TMP/extract.py" <<'PYEOF'
import html
import re
import sys

data = html.unescape(sys.stdin.read())
match = re.search(r'Stok tidak cukup untuk [^"\\<]{1,80}', data)
print(match.group(0) if match else '')
PYEOF

if [ -n "$LOSER_JAR" ]; then
  # Pesan diambil dari payload Inertia (di-escape sebagai HTML), jadi diambil
  # lewat file python terpisah supaya tidak bermasalah dengan escaping shell.
  curl -sS -b "$LOSER_JAR" -c "$LOSER_JAR" "$BASE_URL/co" > "$TMP/loser.html"
  LOSER_MSG="$(python3 "$TMP/extract.py" < "$TMP/loser.html")"
  LOSER_MSG="${LOSER_MSG:-(pesan stok tidak ditemukan)}"
fi

FAILED=0
echo "==> Hasil"
echo "    stok motif sekarang : ${STOCK_NOW} (harus 0, tidak boleh negatif)"
echo "    order draft dibuat  : ${ORDER_COUNT} (harus 1)"
echo "    redirect ke pembayaran: ${WINNERS} (harus 1)"
echo "    pesan untuk yang kalah: ${LOSER_MSG}"

if [ "${STOCK_NOW}" -lt 0 ]; then echo "    ✗ STOK NEGATIF"; FAILED=1; fi
if [ "$ORDER_COUNT" -ne 1 ]; then echo "    ✗ LEBIH DARI SATU ORDER"; FAILED=1; fi
if [ "$WINNERS" -ne 1 ]; then echo "    ✗ PEMENANG TIDAK TERDeteksi"; FAILED=1; fi
if [ "$A_STATUS" = "500" ] || [ "$B_STATUS" = "500" ]; then echo "    ✗ ADA 500 MENTAH"; FAILED=1; fi
case "$LOSER_MSG" in "Stok tidak cukup untuk"*) ;; *) echo "    ✗ PESAN STOK HABIS TIDAK RAMAH UNTUK YANG KALAH"; FAILED=1;; esac

if [ "$FAILED" -eq 0 ]; then
  echo "    ✓ stok aman, satu order, tidak ada 500"
else
  echo "    ✗ ADA MASALAH — lihat detail di atas"
fi

artisan e2e:reset --force >/dev/null
exit $FAILED
