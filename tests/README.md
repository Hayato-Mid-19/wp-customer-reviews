# Customer Reviews Tests

このディレクトリには、WordPress の `WP_UnitTestCase` を使った最小テストを配置する。

## 推奨テスト対象

1. 口コミメタ保存
   - `rating` が 0.5 刻みで正規化されること
   - `linked_post_ids` が許可投稿タイプのみ保存されること
2. REST 一覧取得
   - `sort`, `order`, `page`, `per_page` が反映されること
   - `post_ids` フィルタが機能すること
3. 権限
   - 編集者が口コミを作成/編集できること
   - 権限不足ユーザーが更新できないこと
4. 単体URL制御
   - `review` の single アクセス時に 404 になること

## 実行例

プロジェクト側の test bootstrap が整っている場合:

`phpunit --testsuite customer-reviews`
