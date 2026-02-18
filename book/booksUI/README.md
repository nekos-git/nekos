# Bookshelf3D — システム仕様書

## 概要
- 擬似3D風の本棚 UI プロトタイプ。ブラウザで動作する軽量な React（JSX）実装。
- 主な機能は、背表紙（スパイン）と表紙（フェイチャード本）を混在表示し、マウスオーバーでツールチップ表示すること。

## 主要ファイル
- `index.html` : アプリのエントリ（ルート DOM を提供）。
- `Bookshelf3D.jsx` : 本体コンポーネント。UI ロジック、データ読み込み、レンダリングを実装。
- `Bookshelf3D.css` : スタイル（見た目と擬似3D表現）。
- JSON データ: `001005.json`, `001006.json`, `001010.json`（書籍データ）および各ジャンル定義 `001005genre.json`, `001006genre.json`, `001010genre.json`。

## 動作環境と実行方法
- OS: 任意（ブラウザが実行できればOK）。
- 必要: インターネット接続（外部 API 取得や画像読み込みのため）。
- **重要**: ローカル HTTP サーバーでの実行が**必須**です（`file://` スキームでは動作しません）。

### 実行手順

#### 方法1: Python を使用（推奨・最も簡単）
```bash
cd /任意の場所/booksUI
python -m http.server 8000
```

その後、ブラウザで以下を開く：
```
http://localhost:8000
```

#### 方法2: Node.js を使用
```bash
cd /任意の場所/booksUI
npx http-server
```

#### 方法3: macOS 付属の `python3`
```bash
cd /任意の場所/booksUI
python3 -m http.server 8000
```

### ⚠️ 注意：`file://` スキームでは動作しません
ブラウザで直接 `index.html` を開く（`file:///...`）と、以下のエラーが発生し**動作しません**：
```
Access to XMLHttpRequest at 'file://...' has been blocked by CORS policy: 
Cross origin requests are only supported for protocol schemes: chrome, chrome-extension, 
chrome-untrusted, data, http, https, isolated-app.
```

これは React の Babel トランスパイラと JSON ファイルの読み込みが CORS ポリシーで保護されているためです。必ずローカルサーバー経由でアクセスしてください。

## データフロー
1. 初期状態では `demoShelves`（ダミーデータ）を表示可能。
2. マウント時にまずジャンル定義ファイル（`*genre.json`）を fetch して `genreMap` を構築。
3. `genreMap` が読み込まれたら各書籍 JSON（`001005.json` 等）を fetch し、`shelf`（tech/biz/culture）に振り分けて内部データを構築。
4. 取得した ISBN の塊を openBD API に投げ、サイズ・ページ数などの追加メタを付与。
5. 各カバー画像の自然幅・自然高さからアスペクト比を算出して本の表示サイズを調整。

## 主な設計要素（`Bookshelf3D.jsx`）
- Props
  - `shelves` : 外部から棚データを渡すための配列（デフォルトで `demoShelves` を使用）。
  - `featuredCount` : 各棚の先頭 n 冊を「人気（表紙）」として扱う既定値（デフォルト 3）。
  - `maxSpines` : 表示する背表紙の上限（デフォルト 24）。
  - `randomFeaturedCount` : rest（残り）からランダムで追加表紙にする冊数（デフォルト 3）。

- State
  - `data` : 実際に表示する棚データ（API から構築）。
  - `genreMap` : ジャンルID→ジャンル名のマップ。
  - `tooltip` : 現在表示中のツールチップ位置と本情報。

- 主要ユーティリティ
  - `stableHash(str)` : タイトル等から安定したハッシュを生成して背表紙の色決定に利用。
  - `spineColorFromTitle(title)` : ハッシュを HSL に変換して統一感のある背表紙色を生成。
  - `getSizeStyle(size, pages, aspectRatio)` : サイズ種別・ページ数・アスペクト比から幅・高さ・厚み（影の強さ）を決定。
  - `chunkArray(array, chunkSize)` : ISBN 配列を openBD API 呼び出し用に分割。
  - `shuffleArray(array)` : ランダム表示用シャッフル。

## レンダリングロジック
- 各棚ごとに `featured`（表紙）と `spine`（背表紙）を作成。
- `featuredCount` で先頭を表紙にし、さらに残りから `randomFeaturedCount` をランダムで表紙に昇格させるロジック有り。
- 表示用配列 `mixedBooks` をシャッフルして `uz-mixedRow` に並べる。
- 表紙は `.uz-bookFace`（cover を背景画像に設定）、背表紙は `.uz-spine`（色付きボックス＋縦書きテキスト想定）でレンダリング。

## ツールチップ
- マウスオーバー時に 0.5 秒の遅延で `tooltip` を表示。
- ツールチップは固定位置（`position: fixed`）でマウス座標に追従し、書名・著者・サイズ・ページ数等を表示。

## 外部 API とフォールバック
- openBD API (`https://api.openbd.jp/v1/get?isbn=...`) を利用して書誌情報（ページ数・サイズ等）を取得。
- openBD 取得失敗時でも既存の JSON から棚データは表示するよう例外処理あり。

## 注意点 / 実運用での考慮事項
- **CORS（必読）**: `file://` スキームからの fetch はすべてブロックされます。ローカル JSON ファイル、openBD API、画像読み込みのすべてに CORS 制限が適用されるため、必ず **HTTP サーバー経由**でのアクセスが必須です。
- 画像読み込み: 画像ロード失敗時はデフォルトのアスペクト比（例: 0.7）を使うフォールバックがある。
- パフォーマンス: 大量の画像読み込みや openBD への多数リクエストは遅延の原因。キャッシュや遅延読み込み（lazy load）を検討。
- アクセシビリティ: 現在は視覚に依存した表現が多い。キーボード操作やスクリーンリーダー対応は改善余地あり。

## 拡張案（優先順）
1. 画像の lazy-loading とプレースホルダー適用
2. 表示設定（列数、縮尺、スライス）を UI で調整可能に
3. 詳細モーダル（クリックで詳細表示）やお気に入り機能
4. ジャンル選択フィルタリング、検索機能
5. テスト（レンダリング・ユーティリティ関数）と CI 追加

## 最後に
README 内容の補足・修正や、含めたい技術的詳細があれば指示ください。`Bookshelf3D.jsx` の実装をもとに作成しました。
