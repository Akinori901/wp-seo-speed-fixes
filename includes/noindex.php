<?php
/**
 * 薄い・重複しやすいアーカイブに noindex, follow を付け、クロールを本体の記事に集中させる。
 * - 2ページ目以降（/page/2/ など）
 * - タグ / 日付 / 著者アーカイブ
 * - 検索結果
 * カテゴリの1ページ目と個別記事は index のまま残す。
 *
 * <meta> を直接 echo せず wp_robots フィルタに乗せる。コアや SEO プラグインが出す
 * robots メタと1本にまとまり、二重出力にならない。
 */

if (!defined("ABSPATH")) exit;

add_filter("wp_robots", function ($robots) {
    if (!wssf_enabled("noindex_archives")) return $robots;
    if (is_paged() || is_tag() || is_date() || is_author() || is_search()) {
        unset($robots["index"], $robots["nofollow"]);
        $robots["noindex"] = true;
        $robots["follow"] = true;
    }
    return $robots;
});
