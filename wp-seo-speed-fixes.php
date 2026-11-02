<?php
/**
 * Plugin Name: WP SEO & Speed Fixes
 * Description: Search Console のインデックス指摘と PageSpeed の速度指摘を、設定画面なしで潰す小さな詰め合わせ。Lightning / VK Blocks / Site Kit / Jetpack 環境向け。
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Akinori
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined("ABSPATH")) exit;

/**
 * 機能の ON/OFF。既定値はここが正。
 *
 * 止めたい機能は wp-config.php で定数に並べる（いちばん確実）:
 *   define("WSSF_DISABLE", "code_style,lightning_fonts");
 * もしくは、このプラグインより先に読まれる MU-Plugin から wssf_features フィルタで上書きする。
 *
 * 判定は各フックの実行時に行う（読み込み時に固定しない）。
 * 例外は vk_blocks_separate だけで、VK Blocks が自分の読み込み時に一度だけ問い合わせてくる。
 */
function wssf_features() {
    $features = [
        "attachment_redirect"   => true,  // 添付ページを記事へ301
        "attachment_404_rescue" => true,  // 親を後付けして変わった添付URLの404を救う
        "adopt_thumbnail"       => true,  // アイキャッチ設定時に親なし画像へ親を付ける
        "jetpack_sitemap"       => true,  // Jetpack サイトマップから添付を外す
        "feeds"                 => true,  // タグ/カテゴリ/コメントのフィードを noindex
        "noindex_archives"      => true,  // 2ページ目以降・タグ・日付・著者・検索を noindex
        "lightning_fonts"       => true,  // Lightning が表側で読む Google Fonts を止める
        "sitekit_signin"        => true,  // Site Kit の Sign in with Google を表側で読まない
        "vk_blocks_separate"    => true,  // VK Blocks の CSS を分割読み込みにする
        "code_style"            => false, // <pre> に等幅・横スクロールの最小CSSを足す
    ];
    if (defined("WSSF_DISABLE")) {
        foreach (array_filter(array_map("trim", explode(",", (string)WSSF_DISABLE))) as $key) {
            $features[$key] = false;
        }
    }
    return (array)apply_filters("wssf_features", $features);
}

function wssf_enabled($feature) {
    $features = wssf_features();
    return !empty($features[$feature]);
}

require_once __DIR__ . "/includes/attachments.php";
require_once __DIR__ . "/includes/feeds.php";
require_once __DIR__ . "/includes/noindex.php";
require_once __DIR__ . "/includes/speed.php";
