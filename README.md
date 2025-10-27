# 🚀 Kashiwazaki SEO Schema Content Type Builder

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.2%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.0--dev-orange.svg)](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-schema-content-type-builder/releases)

管理画面から簡単に構造化データ（Schema.org）を設定・出力できるWordPressプラグイン

> 🎯 **GoogleのリッチリザルトやSchema.orgの仕様に準拠した構造化データを、プログラミング知識なしで簡単に実装**

## 主な機能

✨ **4つのContent Typeに対応**
- Article - 一般的な記事（ブログ記事、ニュース以外の記事）
- NewsArticle - ニュース記事（時事的な内容、報道記事）
- BlogPosting - ブログ投稿（個人的な意見、日記形式の記事）
- WebPage - 固定ページ（会社概要、サービス紹介など）

🔧 **柔軟な設定オプション**
- 管理画面でContent Typeごとに投稿タイプを設定
- Google リッチリザルトに準拠した構造化データの自動生成
- パンくずリストの構造化データ対応
- 各Content Type固有のプロパティ設定が可能

📊 **自動取得される情報**
- 記事のURL、タイトル（headline）
- 公開日時（datePublished）、更新日時（dateModified）
- 説明文（description）、アイキャッチ画像（image）
- 著者情報（author）、カテゴリー（articleSection）
- タグ（keywords）、文字数（wordCount）

## 🚀 クイックスタート

### インストール

1. プラグインファイルを `/wp-content/plugins/kashiwazaki-seo-schema-content-type-builder` ディレクトリにアップロード
2. WordPress管理画面の「プラグイン」メニューからプラグインを有効化
3. 管理画面の左側メニューに表示される「Kashiwazaki SEO Schema Content Type Builder」から設定

### 基本設定

**共通設定：**
- Publisher Type（Organization/Person）
- Publisher Name（発行者名）
- Publisher Logo URL（ロゴ画像）
- Default Author Type（Person/Organization）
- パンくずリストの有効化

**Content Type別の設定：**
- **Article**: Article Section（記事のカテゴリー）
- **NewsArticle**: News Keywords（ニュースキーワード）
- **BlogPosting**: コメント数の表示
- **WebPage**: Speakableプロパティ（音声読み上げ対応）

## 使い方

1. 管理画面で「Kashiwazaki SEO Schema Content Type Builder」を開く
2. 各Content Typeに対して、適用する投稿タイプを選択
3. 共通設定でPublisher情報を入力
4. 保存して設定完了

構造化データは各ページの `<head>` タグ内に `<script type="application/ld+json">` として自動出力されます。

### WebPageスキーマの動作について

WebPageスキーマは、ページの種類によって出力されるスキーマタイプが自動的に変わります：

**個別ページ（投稿・固定ページなど）の場合:**
- 「対象の投稿タイプ」で選択した投稿タイプの個別ページに `@type: "WebPage"` が出力されます
- 例：固定ページ、カスタム投稿タイプの個別ページなど

**アーカイブページの場合:**
- 「対象のアーカイブページ」で選択したアーカイブページに `@type: "CollectionPage"` が出力されます
- `CollectionPage`はWebPageの一種で、複数の投稿を一覧表示するページに最適なスキーマタイプです
- 例：ホームページ（投稿一覧）、カテゴリーページ、タグページ、著者アーカイブなど

**CollectionPageスキーマの構造:**
```json
{
  "@context": "https://schema.org",
  "@type": "CollectionPage",
  "url": "ページのURL",
  "name": "ページ名",
  "isPartOf": {
    "@type": "WebSite",
    "url": "サイトURL",
    "name": "サイト名"
  }
}
```

この仕様により、個別ページと一覧ページで最適な構造化データが自動的に出力されます。

## 技術仕様

**システム要件**
- WordPress 5.0以上
- PHP 7.2以上

**対応投稿タイプ**
- 標準の投稿（post）と固定ページ（page）
- すべての公開されているカスタム投稿タイプ

**検証ツール**
- Googleの「リッチリザルトテスト」で構造化データの検証が可能
- Schema.org仕様に完全準拠

## よくある質問

**Q: カスタム投稿タイプにも対応していますか？**
A: はい、公開されているすべての投稿タイプから選択可能です。

**Q: 複数の投稿タイプに同じContent Typeを設定できますか？**
A: はい、例えば「投稿」と「カスタム投稿タイプA」の両方にArticleタイプを設定することができます。

**Q: WebPageを有効化したのに、CollectionPageが出力されるのはなぜですか？**
A: WebPageスキーマでは、個別ページとアーカイブページで出力されるスキーマタイプが異なります。「対象のアーカイブページ」で選択したページ（ホーム、カテゴリー、タグなど）には、WebPageの一種である`CollectionPage`が自動的に出力されます。これはSchema.org仕様に準拠した正しい実装で、複数の投稿を一覧表示するページに最適なスキーマタイプです。

**Q: トップページにWebPageスキーマを出力するにはどうすればいいですか？**
A: WebPageタブで「WebPage Schemaを有効にする」にチェックを入れ、「対象のアーカイブページ」で「ホームページ（投稿一覧）」を選択してください。トップページには`CollectionPage`スキーマが出力されます。

**Q: 構造化データが正しく出力されているか確認する方法は？**
A: Googleの「リッチリザルトテスト」ツールを使用して確認できます。

## 更新履歴

### 1.0.0 - 2025-10-06
- 初回リリース
- Article、NewsArticle、BlogPosting、WebPageの4つのContent Typeに対応
- パンくずリスト構造化データの実装
- 管理画面での柔軟な設定機能

## ライセンス

GPL-2.0-or-later

## サポート・開発者

**開発者**: 柏崎剛 (Tsuyoshi Kashiwazaki)
**ウェブサイト**: https://www.tsuyoshikashiwazaki.jp/
**サポート**: プラグインに関するご質問や不具合報告は、開発者ウェブサイトまでお問い合わせください。

## 🤝 貢献

バグ報告や機能リクエストは、GitHubのIssuesでお願いします。プルリクエストも歓迎します。

## 📞 サポート

技術的なサポートが必要な場合は、開発者ウェブサイトからお問い合わせください。

---

<div align="center">

**🔍 Keywords**: WordPress, SEO, Schema.org, 構造化データ, JSON-LD, リッチリザルト, Article, NewsArticle, BlogPosting, WebPage

Made with ❤️ by [Tsuyoshi Kashiwazaki](https://github.com/TsuyoshiKashiwazaki)

</div>
