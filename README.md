# wp-seo-speed-fixes

[![CI](https://github.com/Akinori901/wp-seo-speed-fixes/actions/workflows/ci.yml/badge.svg)](https://github.com/Akinori901/wp-seo-speed-fixes/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-7.4%E2%80%938.3-777bb4)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

Search Console の「インデックス登録」指摘と、PageSpeed Insights / Site Kit の速度指摘を、
**設定画面なしで**片付ける WordPress 用の小さな詰め合わせです。
Lightning・VK Blocks・Site Kit・Jetpack を使っている、記事数百本規模のブログを想定しています。

> A small drop-in (MU-plugin) for WordPress blogs that cleans up Search Console indexing
> noise (attachment pages, tag/comment feeds, thin archives) and a few PageSpeed findings
> specific to the Lightning theme, VK Blocks and Site Kit. No settings screen; every
> feature can be turned off from `wp-config.php`.

## なぜ作ったか

運用しているブログ（公開記事 約470本）で Search Console を開くと、こうなっていました。

| 指摘 | 件数 | 中身 |
|---|---|---|
| 見つかりませんでした（404） | 57 | 画像の**添付ファイルページ**（`?attachment_id=`）が大半 |
| クロール済み - インデックス未登録 | 485 | **タグ / カテゴリ / 記事コメントのフィードが 334件**（約7割） |
| 同上 | 〃 | タグ・日付などの薄いアーカイブ 104件 |

本体の記事より、画像1枚だけのページやフィードの方が Google のクロールを食っていました。
あわせて Site Kit の「速度」タブでは、テーマとプラグインが読み込む未使用の CSS / JS を指摘されていました。

ひとつずつは数行で直せますが、子テーマや設定画面に散らばると後から追えません。
そこで1つの MU-Plugin にまとめ、**何のために入れたかをコードのコメントに残す**形にしました。

## 何をするか

| 機能キー | 内容 | 対象 |
|---|---|---|
| `attachment_redirect` | 添付ファイルページを **親記事 → アイキャッチに使っている記事 → 画像ファイル** の順で 301。トップには飛ばさない（無関係なページへの 301 はソフト404扱いになるため） | 全サイト |
| `attachment_404_rescue` | 画像に後から親を付けると URL が `/<slug>/` → `/<記事>/<slug>/` に変わり、旧URLが 404 になる。直下1階層の 404 だけを添付のスラッグで引き直して 301 | 全サイト |
| `adopt_thumbnail` | アイキャッチに設定された「親なし画像」に、その記事を親として付ける。REST API で画像を先に上げる投稿ツール対策 | 全サイト |
| `jetpack_sitemap` | Jetpack のサイトマップから添付ページを外し、画像サイトマップを止める | Jetpack |
| `feeds` | タグ / カテゴリ / コメントのフィードへのリンクを head から外し、`X-Robots-Tag: noindex` を返す。サイト全体のフィードは残す | 全サイト |
| `noindex_archives` | 2ページ目以降・タグ・日付・著者・検索結果を `noindex, follow`。`wp_robots` フィルタに乗せるので robots メタが二重にならない | 全サイト |
| `lightning_fonts` | Lightning（origin3）が表側で読む Google Fonts（Lato + Noto Sans JP、約800KB）を止める | Lightning |
| `lightning_image_sizes` | Lightning のヘッダーロゴとトップスライダーの画像は width/height なしで出力される。テーマ設定の画像の実寸をメディア情報から引き、比率と高さを CSS で先に確保して **CLS（レイアウトのずれ）** を防ぐ | Lightning |
| `sitekit_signin` | Site Kit「Sign in with Google」のスクリプト（約100KB）を、ログイン画面以外では読まない | Site Kit |
| `vk_blocks_separate` | VK Blocks の CSS を分割読み込みにする（設定画面の「分割読み込み」と同じ） | VK Blocks |
| `code_style` | `<pre>` に等幅・横スクロールの最小 CSS を足す（**既定 OFF**） | 全サイト |

対象のテーマやプラグインが無い場合、その機能は何もしません。

### 速度まわりの実測

ローカルの Lighthouse 12（モバイル）で、このプラグインの分だけを比べた値です。

- `vk_blocks_separate`: 「使用していない CSS」が **約67KB → 約42KB**。全ブロック分の `block-build.css`（24KB）が読まれなくなります
- `lightning_fonts`: 「CSS の最小化」の指摘がこれ1件だったため、消えました
- `lightning_image_sizes`: CLS が **0.055 → 0**（ローカル3回とも）。PageSpeed の低速回線では 0.261（悪い）と出ていたものです。
  原因はロゴでした。ロゴが届くまでヘッダーの高さは約14pxしかなく、届いた瞬間に約46px伸びてページ全体が下へずれていました（トレースで確認）

なお LCP（最大コンテンツの描画）の改善は、ほぼトップ画像の差し替え（1.4MB の PNG → 37KB の WebP）によるものです。
このプラグインの効果ではありません。

### やらないこと

- AdSense や Google アナリティクス（gtag）のスクリプトは触りません。使っている限り「使用していない JavaScript」の指摘は消えません。これは Google 自身のスクリプトの性質によるものです
- canonical・OGP・meta description は出力しません（VK All in One Expansion Unit や SEO プラグインに任せる前提です）
- ページキャッシュはしません。サーバーの応答時間（TTFB）が遅い場合は、別途キャッシュプラグインを入れてください

## インストール（MU-Plugin 推奨）

```sh
cd wp-content/mu-plugins
git clone https://github.com/Akinori901/wp-seo-speed-fixes.git
cp wp-seo-speed-fixes/mu-loader.php wp-seo-speed-fixes-loader.php
```

MU-Plugin は `mu-plugins/` 直下のファイルしか読まれないため、ローダーを1枚置きます。

**通常のプラグインとして入れることもできますが、その場合 `vk_blocks_separate` は効きません。**
VK Blocks はプラグイン本体を読み込んだ時点で1回だけ分割読み込みの可否を問い合わせますが、
プラグインはアルファベット順に読み込まれるため、このプラグインが VK Blocks より後になります。
MU-Plugin はすべての通常プラグインより先に読み込まれるので、この問題が起きません。

## 機能を止める

`wp-config.php` に、止めたい機能キーをカンマ区切りで書きます（`require_once ABSPATH . 'wp-settings.php';` より上に）。

```php
define("WSSF_DISABLE", "lightning_fonts,noindex_archives");
```

`code_style` を有効にしたい場合や、条件付きで切り替えたい場合は、このプラグインより先に読まれる MU-Plugin で
`wssf_features` フィルタを使います。

```php
add_filter("wssf_features", function ($features) {
    $features["code_style"] = true;
    return $features;
});
```

## 入れたあとに

1. Search Console で「検証を開始」を押す（404 と「クロール済み - インデックス未登録」）
2. フィードと添付ページは、Google が再クロールするまで件数が残ります。減るのは数週間単位です
3. 親を後付けした添付がある場合は、旧URL が 301 されているかをいくつか開いて確認してください

## テスト

使い捨ての WordPress（Docker）に MU-Plugin として入れ、実際の HTTP 応答を確かめます。

```sh
tests/smoke.sh
```

確かめていること: 添付ページの 301 と行き先 / アイキャッチ設定で親が付くこと /
親付け前の旧URL の救済 / 無関係な 404 には触らないこと / 各アーカイブの noindex /
フィードの `X-Robots-Tag` / Lightning のロゴ・スライダーに実寸の比率が付くこと /
`WSSF_DISABLE` で個別に止められること。

CI では、これに加えて PHP 7.4 / 8.1 / 8.3 で構文チェックをしています。

## ライセンス

GPL-2.0-or-later（WordPress 本体と同じ）
