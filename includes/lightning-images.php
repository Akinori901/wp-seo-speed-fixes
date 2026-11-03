<?php
/**
 * Lightning のヘッダーロゴとトップスライダーの画像に、実寸の比率を先に与えて CLS を防ぐ。
 *
 * Lightning はどちらの <img> も width/height なしで出力し、HTML を差し替えるフックも無い。
 * ロゴが届くまでヘッダーは高さ十数px しかなく、届いた瞬間に伸びてページ全体が下へずれる
 * （筆者のサイトではこれ1つで CLS 0.055、PageSpeed の低速回線では 0.26 まで悪化した）。
 *
 * テーマ設定に登録された画像の実寸をメディア情報（無ければファイル本体）から引き、CSS で枠を確保する。
 * aspect-ratio は "auto" 付きなので、画像が届いた後は実寸の比率が優先される。
 */

if (!defined("ABSPATH")) exit;

/**
 * 画像 URL の実寸 [幅, 高さ]。分からなければ null。
 * 毎リクエストの DB 照会を避けるため、URL ごとに1週間キャッシュする（差し替えれば URL が変わる）。
 */
function wssf_image_size($url) {
    if (!$url) return null;
    $key = "wssf_imgsize_" . md5($url);
    $cached = get_transient($key);
    if (is_array($cached)) return $cached ?: null;

    $size = [];
    $id = attachment_url_to_postid($url);
    $meta = $id ? wp_get_attachment_metadata($id) : null;
    if (!empty($meta["width"]) && !empty($meta["height"])) {
        $size = [(int)$meta["width"], (int)$meta["height"]];
    } else {
        // メディアライブラリを通さずアップロードされた画像（FTP で置いた差し替え画像など）
        $uploads = wp_get_upload_dir();
        if (strpos($url, $uploads["baseurl"]) === 0) {
            $file = $uploads["basedir"] . substr($url, strlen($uploads["baseurl"]));
            $info = is_readable($file) ? @getimagesize($file) : false;
            if ($info && $info[0] && $info[1]) $size = [(int)$info[0], (int)$info[1]];
        }
    }
    set_transient($key, $size, WEEK_IN_SECONDS);
    return $size ?: null;
}

add_action("wp_head", function () {
    if (!wssf_enabled("lightning_image_sizes")) return;
    $options = get_option("lightning_theme_options");
    if (!is_array($options)) return;

    $css = "";

    // ロゴ: テーマの max-height（カスタマイザーの PC/モバイル値）はそのまま効く。
    // height に実寸を入れて枠を確保し、max-height で縮んだときは object-fit で歪ませない。
    if (!empty($options["head_logo"])) {
        $logo = apply_filters("lightning_head_logo_image_url", $options["head_logo"]);
        if ($size = wssf_image_size($logo)) {
            $css .= sprintf(
                ".site-header-logo img{height:%dpx;aspect-ratio:auto %d/%d;object-fit:contain;}",
                $size[1], $size[0], $size[1]
            );
        }
    }

    // スライダー: 幅100%・高さ auto で表示されるので、比率だけ与えれば高さが決まる。
    // スライドごとに比率が違っても、到着後は "auto" で実寸比になる。枠は1枚目に合わせる。
    if (is_front_page()) {
        for ($i = 1; $i <= 5; $i++) {
            if (empty($options["top_slide_image_" . $i])) continue;
            if ($size = wssf_image_size($options["top_slide_image_" . $i])) {
                $css .= sprintf(".ltg-slide-item-img{aspect-ratio:auto %d/%d;}", $size[0], $size[1]);
            }
            break;
        }
    }

    if ($css) echo "<style id=\"wssf-lightning-image-sizes\">" . $css . "</style>\n";
}, 20);
