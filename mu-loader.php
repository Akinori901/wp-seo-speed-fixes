<?php
/**
 * MU-Plugin 用ローダー。
 *
 * MU-Plugin は wp-content/mu-plugins/ 直下のファイルしか読まれないので、
 * このファイルを wp-content/mu-plugins/wp-seo-speed-fixes-loader.php として置き、
 * リポジトリ一式を wp-content/mu-plugins/wp-seo-speed-fixes/ に置く。
 */

if (!defined("ABSPATH")) exit;

require_once __DIR__ . "/wp-seo-speed-fixes/wp-seo-speed-fixes.php";
