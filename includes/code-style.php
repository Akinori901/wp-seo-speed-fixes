<?php
/**
 * コードブロック（<pre>）の最低限の体裁。既定は OFF。
 *
 * 古いシンタックスハイライト系プラグイン（Crayon Syntax Highlighter など）は PHP 8 系で
 * 記事ごと 500 を返すことがある。止めたあとに素の <pre> が読みにくくなるのを補う用途。
 * 等幅・横スクロール・行間だけを整え、色付けはしない。
 */

if (!defined("ABSPATH")) exit;

add_action("wp_head", function () {
    if (!wssf_enabled("code_style")) return;
    echo "\n<style id=\"wssf-code-style\">\n"
       . ".entry-content pre, .post_content pre, article pre{"
       . "font-family:SFMono-Regular,Consolas,Liberation Mono,Menlo,monospace;"
       . "font-size:13px;line-height:1.6;overflow-x:auto;white-space:pre;"
       . "word-break:normal;word-wrap:normal;-webkit-overflow-scrolling:touch;"
       . "background:#f6f8fa;border:1px solid #d0d7de;border-radius:6px;padding:12px 14px;}"
       . ".entry-content pre code, article pre code{white-space:pre;}"
       . "</style>\n";
}, 20);
