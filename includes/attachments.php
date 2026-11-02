<?php
/**
 * 添付ファイルページの後始末。
 *
 * WordPress は画像1枚ごとに「添付ファイルページ」を持つ。中身は画像1枚だけの薄いページで、
 * Search Console の「404」「クロール済み - インデックス未登録」の常連になる。
 * ここでは添付ページを本来の記事へ 301 で寄せ、サイトマップからも外す。
 */

if (!defined("ABSPATH")) exit;

/**
 * 添付の「行き先」になる記事ID。親記事 → その画像をアイキャッチにしている公開記事 の順。
 * REST API で画像を先に上げてから記事を作るツールだと、画像に親が付かないことが多い。
 */
function wssf_attachment_owner($att_id) {
    $att = get_post($att_id);
    if ($att && $att->post_parent) return (int)$att->post_parent;
    global $wpdb;
    return (int)$wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
         WHERE m.meta_key = '_thumbnail_id' AND m.meta_value = %d AND p.post_status = 'publish'
         ORDER BY p.ID LIMIT 1",
        $att_id
    ));
}

/**
 * 添付ファイルページを 301 で転送する。
 * 行き先は 親記事 → アイキャッチ利用先の記事 → 画像ファイル本体 の順。
 * トップページへ飛ばさないこと: 無関係なページへの 301 は Google にソフト404と判定される。
 */
add_action("template_redirect", function () {
    if (!wssf_enabled("attachment_redirect") || !is_attachment()) return;
    global $post;
    $owner = $post ? wssf_attachment_owner($post->ID) : 0;
    if ($owner) {
        wp_safe_redirect(get_permalink($owner), 301);
    } elseif ($post && ($file = wp_get_attachment_url($post->ID))) {
        wp_safe_redirect($file, 301);
    } else {
        wp_safe_redirect(home_url("/"), 301);
    }
    exit;
}, 1);

/**
 * 親を後から付けた添付は、URL が /<slug>/ から /<記事のパーマリンク>/<slug>/ に変わる。
 * 旧URL はサイトマップ経由で Google に知られているので、そのままだと 404 が積み上がる。
 * サイト直下1階層の 404 だけを対象に、添付のスラッグで引き直して転送する。
 */
add_action("template_redirect", function () {
    if (!wssf_enabled("attachment_404_rescue") || !is_404()) return;
    $home = trailingslashit(parse_url(home_url(), PHP_URL_PATH) ?: "/");
    $path = (string)parse_url($_SERVER["REQUEST_URI"] ?? "", PHP_URL_PATH);
    if (strpos($path, $home) !== 0) return;
    $slug = trim(substr($path, strlen($home)), "/");
    if ($slug === "" || strpos($slug, "/") !== false) return;
    global $wpdb;
    $id = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_name = %s LIMIT 1",
        sanitize_title($slug)
    ));
    if (!$id) return;
    $owner = wssf_attachment_owner($id);
    $to = ($owner && get_post_status($owner) === "publish") ? get_permalink($owner) : wp_get_attachment_url($id);
    if ($to) {
        wp_safe_redirect($to, 301);
        exit;
    }
}, 2);

/**
 * アイキャッチに設定された親なし画像へ、その記事を親として付ける。
 * 以後は上の転送が親記事を直接引けるようになる。
 */
function wssf_adopt_thumbnail($meta_id, $post_id, $meta_key, $meta_value) {
    if ($meta_key !== "_thumbnail_id" || !wssf_enabled("adopt_thumbnail")) return;
    $att = get_post((int)$meta_value);
    if (!$att || $att->post_type !== "attachment" || $att->post_parent) return;
    wp_update_post(["ID" => $att->ID, "post_parent" => (int)$post_id]);
}
add_action("added_post_meta", "wssf_adopt_thumbnail", 10, 4);
add_action("updated_post_meta", "wssf_adopt_thumbnail", 10, 4);

/**
 * Jetpack サイトマップから添付ページを外す。
 * どれも上で 301 する URL なので、サイトマップに載せると矛盾した合図になる。
 */
add_filter("jetpack_sitemap_skip_post", function ($skip, $post) {
    if (!wssf_enabled("jetpack_sitemap")) return $skip;
    return ($post && $post->post_type === "attachment") ? true : $skip;
}, 10, 2);

/**
 * 画像サイトマップは各添付ページを <loc> に載せる作りなので丸ごと出さない。
 * 画像は記事本文から辿られるため、画像検索への露出は落ちない。
 */
add_filter("jetpack_sitemap_image_skip_post", function ($skip) {
    return wssf_enabled("jetpack_sitemap") ? true : $skip;
});
