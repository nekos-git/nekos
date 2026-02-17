#!/bin/bash

# GitHub自動アップロードスクリプト
echo "=== GitHub自動アップロード開始 ==="

# ディレクトリを移動
cd /Users/abejunichi/Desktop/github

# 現在の状態を表示
echo "現在のディレクトリ: $(pwd)"
echo "Git状態を確認中..."

# すべての変更をステージング
echo "ファイルをステージング中..."
git add .

# コミットメッセージを自動生成
TIMESTAMP=$(date "+%Y-%m-%d %H:%M:%S")
COMMIT_MSG="Auto commit - $TIMESTAMP"

echo "コミット中: $COMMIT_MSG"
git commit -m "$COMMIT_MSG"

# リモートリポジトリの確認
echo "リモートリポジトリ情報:"
git remote -v

# GitHubにプッシュ
echo "GitHubにプッシュ中..."
git push origin main

echo "=== アップロード完了 ==="
