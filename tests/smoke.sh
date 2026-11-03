#!/usr/bin/env bash
# 使い捨ての WordPress にプラグインを MU-Plugin として入れ、実際の HTTP 応答で挙動を確かめる。
# 使い方: tests/smoke.sh   （Docker が必要。終わるとコンテナとボリュームを消す）
set -euo pipefail

cd "$(dirname "$0")"
BASE="http://localhost:8089"
# 追加の compose ファイルは SMOKE_COMPOSE_EXTRA で渡せる（ローカルでサブネットを指定したいとき等）
DC="docker compose -p wssf-smoke -f compose.yaml${SMOKE_COMPOSE_EXTRA:+ -f $SMOKE_COMPOSE_EXTRA}"
FAIL=0

cleanup() { $DC down -v >/dev/null 2>&1 || true; }
trap cleanup EXIT
[ -n "${SMOKE_KEEP:-}" ] && trap - EXIT  # 調査用に環境を残す

wp() { $DC exec -T cli wp "$@"; }
pass() { echo "  ok   $1"; }
fail() { echo "  NG   $1"; FAIL=1; }

# $1=説明 $2=URL $3=期待するステータス $4=Location に含まれるべき文字列（任意）
expect_redirect() {
    local out code loc
    out=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$2")
    code=${out%% *}; loc=${out#* }
    if [ "$code" = "$3" ] && [[ "$loc" == *"${4:-}"* ]]; then pass "$1"; else fail "$1 (got $code $loc)"; fi
}
# $1=説明 $2=URL $3=yes|no （robots メタに noindex が出るべきか）
expect_noindex() {
    # curl | grep -q だと grep が先に抜けて curl が SIGPIPE で落ち、pipefail で誤判定になる。一度受けてから見る。
    local hit=no body
    body=$(curl -s "$2")
    grep -qiE '<meta name=.robots. content=.[^>]*noindex' <<<"$body" && hit=yes
    if [ "$hit" = "$3" ]; then pass "$1"; else fail "$1 (noindex=$hit)"; fi
}
# $1=説明 $2=URL $3=yes|no （X-Robots-Tag: noindex が付くべきか）
expect_header_noindex() {
    local hit=no headers
    headers=$(curl -s -D - -o /dev/null "$2")
    grep -qi '^x-robots-tag:.*noindex' <<<"$headers" && hit=yes
    if [ "$hit" = "$3" ]; then pass "$1"; else fail "$1 (x-robots-tag noindex=$hit)"; fi
}

echo "== 起動"
$DC up -d --quiet-pull --wait db wp >/dev/null
$DC up -d --quiet-pull cli >/dev/null
for _ in $(seq 1 30); do curl -s -o /dev/null "$BASE" && break; sleep 1; done

wp core install --url="$BASE" --title=smoke --admin_user=admin --admin_password=admin \
    --admin_email=admin@example.com --skip-email >/dev/null
wp rewrite structure '/%postname%/' --hard >/dev/null
# WP 6.4 以降の新規サイトは添付ページ自体が無効。対象は「添付ページが生きている既存サイト」なので有効にする。
wp option update wp_attachment_pages_enabled 1 >/dev/null
wp rewrite flush --hard >/dev/null 2>&1

echo "== データ作成"
POST=$(wp post create --post_title=hello --post_status=publish --tags_input=sample --porcelain)
for i in $(seq 1 12); do wp post create --post_title="filler $i" --post_status=publish --porcelain >/dev/null; done
# 1x1 PNG を親なしで取り込む（REST で画像を先に上げるツールと同じ状態）
$DC exec -T cli sh -c 'printf "%s" "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==" | base64 -d > /tmp/pixel.png'
ATT=$(wp media import /tmp/pixel.png --title=pixel --porcelain)
ATT_SLUG=$(wp post get "$ATT" --field=post_name)
# パーマリンク構造が反映されてから検査する（未反映だとアーカイブが 404 のまま判定される）
for _ in $(seq 1 10); do [ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/tag/sample/")" = 200 ] && break; sleep 1; done

echo "== 添付"
expect_redirect "親なし・未使用の添付ページは画像ファイルへ301" "$BASE/?attachment_id=$ATT" 301 "/wp-content/uploads/"
OLD_URL="$BASE/$ATT_SLUG/"
wp post meta set "$POST" _thumbnail_id "$ATT" >/dev/null
PARENT=$(wp post get "$ATT" --field=post_parent)
[ "$PARENT" = "$POST" ] && pass "アイキャッチ設定で添付に親が付く" || fail "アイキャッチ設定で添付に親が付く (parent=$PARENT)"
expect_redirect "添付ページは親記事へ301" "$BASE/?attachment_id=$ATT" 301 "/hello/"
expect_redirect "親付け前の添付URL（今は404になる）を親記事へ救済" "$OLD_URL" 301 "/hello/"
expect_redirect "無関係な404はそのまま404" "$BASE/no-such-page/" 404

echo "== noindex"
expect_noindex "個別記事は index のまま" "$BASE/hello/" no
expect_noindex "タグアーカイブは noindex" "$BASE/tag/sample/" yes
expect_noindex "2ページ目以降は noindex" "$BASE/page/2/" yes
expect_noindex "検索結果は noindex" "$BASE/?s=hello" yes

echo "== フィード"
expect_header_noindex "サイト全体のフィードは残す" "$BASE/feed/" no
expect_header_noindex "タグのフィードは noindex" "$BASE/tag/sample/feed/" yes
expect_header_noindex "記事コメントのフィードは noindex" "$BASE/hello/feed/" yes
TAG_HTML=$(curl -s "$BASE/tag/sample/")
if grep -q 'tag/sample/feed' <<<"$TAG_HTML"; then
    fail "タグアーカイブの head にタグフィードのリンクを出さない"
else
    pass "タグアーカイブの head にタグフィードのリンクを出さない"
fi

echo "== Lightning のロゴ・スライダー画像の枠（CSS はテーマ設定値だけで決まるので本体なしで確かめる）"
$DC exec -T cli sh -c 'printf "%s" "iVBORw0KGgoAAAANSUhEUgAAAAQAAAACCAIAAADwyuo0AAAAEElEQVR4nGP4z8AARwzIHABvqgf5gNwAKAAAAABJRU5ErkJggg==" | base64 -d > /tmp/wide.png'
WIDE=$(wp media import /tmp/wide.png --title=wide --porcelain)
WIDE_URL=$(wp eval "echo wp_get_attachment_url($WIDE);")
wp option update lightning_theme_options "{\"head_logo\":\"$WIDE_URL\",\"top_slide_image_1\":\"$WIDE_URL\"}" --format=json >/dev/null
FRONT_HTML=$(curl -s "$BASE/")
POST_HTML=$(curl -s "$BASE/hello/")
grep -q '\.site-header-logo img{height:2px;aspect-ratio:auto 4/2;' <<<"$FRONT_HTML" \
    && pass "ロゴに実寸の高さと比率が付く" || fail "ロゴに実寸の高さと比率が付く"
grep -q '\.ltg-slide-item-img{aspect-ratio:auto 4/2;}' <<<"$FRONT_HTML" \
    && pass "トップページのスライダーに比率が付く" || fail "トップページのスライダーに比率が付く"
grep -q '\.site-header-logo img{' <<<"$POST_HTML" && ! grep -q 'ltg-slide-item-img' <<<"$POST_HTML" \
    && pass "記事ページはロゴだけ（スライダーはトップのみ）" || fail "記事ページはロゴだけ（スライダーはトップのみ）"

echo "== WSSF_DISABLE で個別に止められる"
wp config set WSSF_DISABLE "noindex_archives,attachment_redirect,lightning_image_sizes" >/dev/null
sleep 3  # 公式イメージは opcache.revalidate_freq=2。書き換え直後は古い wp-config.php が使われる
expect_noindex "noindex_archives を止めるとタグは index" "$BASE/tag/sample/" no
ATT_URL=$(wp eval "echo get_attachment_link($ATT);")
expect_redirect "attachment_redirect を止めると添付ページは200" "$ATT_URL" 200
expect_header_noindex "止めていない feeds は効いたまま" "$BASE/tag/sample/feed/" yes
grep -q 'wssf-lightning-image-sizes' <<<"$(curl -s "$BASE/")" \
    && fail "lightning_image_sizes を止めると枠の CSS が出ない" || pass "lightning_image_sizes を止めると枠の CSS が出ない"
wp config delete WSSF_DISABLE >/dev/null

echo
if [ "$FAIL" = 0 ]; then echo "ALL PASSED"; else echo "FAILED"; exit 1; fi
