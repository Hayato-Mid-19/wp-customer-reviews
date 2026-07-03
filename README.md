# Customer Reviews Plugin 要件定義 / 実装メモ

## 目的

WordPress の通常投稿とは別管理で「口コミ（レビュー）」を作成・編集し、指定した投稿（複数可）に紐付けて API 配信する。

## 要件サマリー（確定）

1. 口コミは通常投稿と別画面で作成/編集する。
2. 紐付け対象は「カテゴリ/タグ」ではなく「特定の投稿（複数選択）」とする。
3. 口コミ編集画面で投稿を選択する前に、別途プラグイン設定画面で「紐付け対象の投稿属性（対象投稿タイプ等）」を設定できるようにする。
   - 設定画面は将来的に他機能の設定も持てる構成にする。
4. 口コミデータは推奨方式（カスタム投稿タイプ）で保存する。
5. `single review` は 404 を返し、単体ページは表示しない。
6. API エンドポイントは `/wp-json/customer-reviews/v1/review/...` で提供する。
7. API は並び順（新着/評価順）、ページング、件数上限をパラメータ指定可能にする。
8. API 返却項目は以下を含む。
   - レビュアー名
   - 属性（年代・職種など）
   - 評価（0.0〜5.0 / 0.5 刻み）
   - プロフィールタグ（複数）
   - タイトル
   - 本文
   - 投稿日
   - 更新日
9. 権限は「編集者以上」で口コミの作成/編集/削除を可能にする。
10. プロフィールタグは専用タクソノミーとして実装し、他投稿へ影響させない。
11. 表示テンプレート（HTML/CSS）や SCSS ビルド運用は不要（API 配信前提）。
12. セキュリティ/品質は推奨構成で実装し、最低限のテストを用意する。

## データ設計

### カスタム投稿タイプ

- 投稿タイプ: `review`
- 想定公開設定:
  - `public: false`
  - `show_ui: true`
  - `show_in_rest: true`
  - `exclude_from_search: true`
  - `publicly_queryable: true`（single 到達時はプラグイン側で 404）

### メタ項目

- `reviewer_name`（テキスト）
- `reviewer_attribute`（テキスト）
- `rating`（数値: 0.0〜5.0 / 0.5 step）
- `review_title`（複数行テキスト）
- `review_body`（複数行テキスト）
- `linked_post_ids`（投稿 ID 配列）

### 専用タクソノミー

- タクソノミー: `review_profile_tag`
- 対象投稿タイプ: `review`
- 口コミ専用で運用し、他投稿タイプには紐付けない。
- 初期タグはプラグイン有効化時と管理画面初期化時に不足分のみ自動投入する。
- 投稿編集画面では新規タグを追加できず、事前登録済みタグのみ選択可能。

## 設定画面（プラグイン別画面）

### 目的

- 口コミが紐付け可能な「投稿属性（対象投稿タイプ等）」を事前設定する。
- 将来的な機能追加に耐えられる設定セクション構成を採用する。

### 現時点の必須設定

- 口コミ紐付け対象の投稿タイプ選択（複数可を想定）

### 将来拡張（未確定）

- API デフォルト件数
- API 件数上限
- 権限の詳細設定
- 監査ログ/運用設定

## API 仕様（v1）

### ベース

- `/wp-json/customer-reviews/v1/review`

### 主なクエリパラメータ

- `post_ids`: 紐付け対象投稿 ID（カンマ区切りまたは配列）
- `sort`: `newest` / `rating`
- `order`: `asc` / `desc`
- `page`: ページ番号
- `per_page`: 1ページ件数（上限あり）

### 返却項目

- `id`
- `reviewer_name`
- `reviewer_attribute`
- `rating`
- `profile_tags`（専用タクソノミー）
- `title`
- `body`
- `date_published`
- `date_updated`
- `linked_post_ids`

## 権限設計

- 口コミの CRUD は編集者以上を許可。
- REST API の管理系操作（作成/更新/削除）は権限チェックを必須化。
- 公開取得 API（GET）は要件に応じて公開とする（詳細は実装時に固定）。

## バリデーション

- `rating` は 0.0〜5.0 かつ 0.5 刻み以外を拒否。
- テキスト入力はサニタイズを実施。
- ID 配列（`linked_post_ids`）は整数化・存在確認を実施。
- タクソノミー入力は許可済み項目のみ受理。

## セキュリティ方針（推奨）

- 管理画面更新時の nonce 検証
- capability チェック（編集者以上）
- REST permission callback の実装
- 出力時エスケープの徹底
- 不正パラメータ時の適切なエラーレスポンス

## 品質方針（推奨）

- 最低限のテストを実装:
  - 口コミ保存テスト（メタ/タクソノミー/投稿紐付け）
  - REST 取得テスト（ソート/ページング/上限）
  - 権限テスト（編集者可、権限不足拒否）
  - バリデーションテスト（rating step 等）

## 実装済み構成（初版）

- プラグイン本体: `customer-reviews.php`
- メインオーケストレーター: `includes/class-customer-reviews-plugin.php`
- 設定画面: `includes/class-customer-reviews-settings.php`
- 口コミ CPT/タクソノミー: `includes/class-customer-reviews-review-post-type.php`
- 口コミ入力項目（メタボックス）: `includes/class-customer-reviews-review-meta-box.php`
- REST API: `includes/class-customer-reviews-rest-controller.php`
- single 404 制御: `includes/class-customer-reviews-single-blocker.php`
- テスト雛形: `tests/`

## 管理画面の使い方

1. プラグインを有効化する
2. `設定 > Customer Reviews` で「紐付け可能な投稿タイプ」と API の `default_per_page` / `max_per_page` を設定
3. `Reviews` で口コミを作成
4. 口コミ編集画面で以下を入力
   - レビュアー名
   - 属性
   - 評価（0.0〜5.0 / 0.5 step）
   - プロフィールタグ（チェックボックス複数選択）
   - タイトル（複数行）
   - 本文（複数行）
   - 紐付け投稿（複数選択）
   - 実在性チェック（3項目）

## API 利用例

- 一覧:
  - `/wp-json/customer-reviews/v1/review`
- 投稿 ID 123 に紐づく口コミを新着順:
  - `/wp-json/customer-reviews/v1/review?post_ids=123&sort=newest&order=desc`
- 投稿 ID 123,456 に紐づく口コミを評価順:
  - `/wp-json/customer-reviews/v1/review?post_ids=123,456&sort=rating&order=desc&page=1&per_page=20`
- 単体:
  - `/wp-json/customer-reviews/v1/review/789`

## 現在の既知仕様

- `review` の single URL は常に 404。
- API の GET は公開（`permission_callback: __return_true`）。
- 口コミの管理権限は編集者以上（管理者含む）。
- `rating` は保存時に 0.5 刻みへ正規化される。
- `review` では WordPress 標準のタイトル/本文入力欄を非表示（メタボックス入力のみ）。
- `review_profile_tag` の標準メタボックスは非表示。投稿画面では新規タグ追加不可。
- プロフィールタグ一覧は `term_id` 昇順で表示。
