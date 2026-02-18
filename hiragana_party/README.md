# ひらがなパーティー - WebSocket版（40人同時接続対応）

Deno Deploy用のWebSocketサーバーとフロントエンドのセットです。

## 機能

- **40人同時接続対応**（元の20人から増加）
- リアルタイムWebSocket通信
- ポーリング不要で低遅延
- サーバー時刻同期機能
- 自動再接続対応（実装可能）

## ファイル構成

- `server.ts` - Deno Deploy用WebSocketサーバー
- `hiragana_party_ws.html` - WebSocket対応フロントエンド

## Deno Deployへのデプロイ手順

### 1. Deno Deployアカウント作成
https://deno.com/deploy にアクセスしてアカウント作成

### 2. プロジェクト作成
1. "New Project"をクリック
2. GitHubリポジトリを接続するか、直接コードをアップロード

### 3. server.tsをデプロイ
- エントリーポイント: `server.ts`
- Deno Deployが自動的に検出してデプロイ

### 4. WebSocket URLを更新
デプロイ後、フロントエンドの`hiragana_party_ws.html`を編集:

```javascript
// この行を変更
const WS_URL = 'ws://localhost:8000/ws';

// デプロイしたURLに変更（例）
const WS_URL = 'wss://your-project-name.deno.dev/ws';
```

## ローカルでのテスト

```bash
# Deno をインストール（まだの場合）
curl -fsSL https://deno.land/install.sh | sh

# サーバー起動
deno run --allow-net --allow-env server.ts

# ブラウザで開く
open hiragana_party_ws.html
```

## WebSocketメッセージプロトコル

### クライアント → サーバー

- `create` - ルーム作成
- `join` - ルーム参加
- `start` - ゲーム開始（ホストのみ）
- `complete` - 問題完了
- `next` - 次の問題へ（ホストのみ）
- `ping` - サーバー時刻同期

### サーバー → クライアント

- `created` - ルーム作成完了
- `joined` - 参加完了
- `players` - プレイヤーリスト更新
- `state` - ゲーム状態更新
- `started` - ゲーム開始通知
- `pong` - Ping応答
- `error` - エラー通知

## 主な改善点

### 元の仕様（ポーリング版）
- HTTP RESTポーリング
- 20人まで対応
- サーバー負荷が高い（頻繁なリクエスト）

### 新仕様（WebSocket版）
- リアルタイムWebSocket通信
- 40人まで対応
- サーバー負荷が低い（常時接続）
- レスポンスが即座

## カスタマイズ

### 最大人数を変更
`server.ts`の以下の行を編集:

```typescript
const MAX_PLAYERS = 40; // お好みの数に変更
```

フロントエンドの表示も更新:

```html
<h3>参加者 (<span id="player-count">0</span>/40)</h3>
```

## トラブルシューティング

### WebSocketに接続できない
- URLが正しいか確認（`ws://`はローカル、`wss://`は本番）
- CORSの問題がないか確認
- ブラウザのコンソールでエラーを確認

### ゲームが進まない
- サーバー時刻同期を確認
- ブラウザのデベロッパーツールでWebSocketメッセージを確認

## ライセンス

既存のひらがなパーティーと同じライセンス
