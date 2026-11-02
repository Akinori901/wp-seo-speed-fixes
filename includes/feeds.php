<?php
/**
 * フィードの整理。
 *
 * タグやカテゴリを多く持つブログでは、タグ別・カテゴリ別・記事ごとのコメントのフィードが
 * 「クロール済み - インデックス未登録」の大半を占めることがある（筆者のサイトでは約7割）。
 * - head の追加フィードリンク（タグ/カテゴリ/コメント）を出さない → 発見経路を断つ
 * - サイト全体のフィード以外には X-Robots-Tag: noindex を返す
 * サイト全体のフィードは購読者向けに残す。
 */

if (!defined("ABSPATH")) exit;

// feed_links_extra は wp_head の優先度3。それより先に外す（WP_Hook は実行中の追加・削除に対応している）。
add_action("wp_head", function () {
    if (wssf_enabled("feeds")) remove_action("wp_head", "feed_links_extra", 3);
}, 0);

add_filter("feed_links_show_comments_feed", function ($show) {
    return wssf_enabled("feeds") ? false : $show;
});

add_action("template_redirect", function () {
    if (!wssf_enabled("feeds") || !is_feed()) return;
    if (is_archive() || is_singular() || is_comment_feed() || is_search()) {
        header("X-Robots-Tag: noindex, follow", true);
    }
}, 0);
