<?php
/**
 * PageSpeed の「使用していない CSS / JavaScript」「CSS の最小化」への対処。
 * どれも対象のテーマ・プラグインが無ければ何も起きない。
 */

if (!defined("ABSPATH")) exit;

/**
 * Lightning（origin3 スキン）が表側の wp_footer で読む Google Fonts（Lato + Noto Sans JP、約800KB）を止める。
 * テーマ CSS の font-family はヒラギノ / メイリオへフォールバックするので、レイアウトは崩れない。
 * 管理画面側（admin_footer）は触らない。
 */
add_action("after_setup_theme", function () {
    if (wssf_enabled("lightning_fonts")) remove_action("wp_footer", "lightning_origin3_load_fonts");
}, 20);

/**
 * Site Kit「Sign in with Google」の gsi/client（約100KB）を表側では読まない。
 * One Tap やコメント欄のボタンを使っていなければ表側では不要。ログイン画面では従来どおり出す。
 */
add_filter("googlesitekit_sign-in-with-google_tag_blocked", function ($blocked) {
    if (!wssf_enabled("sitekit_signin")) return $blocked;
    return $blocked || !doing_action("login_init");
});

/**
 * VK Blocks の CSS を「分割読み込み」にする（設定画面の load_separate_option と同じ切替）。
 * 既定の結合読み込みでは、全ブロック分の block-build.css を全ページで読む。
 *
 * VK Blocks はプラグイン本体の読み込み時に一度だけこのフィルタを問い合わせる。
 * 通常のプラグインとして入れると読み込み順が VK Blocks より後になり効かないため、
 * MU-Plugin として入れること（README 参照）。
 */
add_filter("vk_blocks_should_load_separate_assets", function ($separate) {
    return wssf_enabled("vk_blocks_separate") ? true : $separate;
});
