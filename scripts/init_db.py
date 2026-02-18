#!/usr/bin/env python3
"""
SQLite DB初期化 + 既存JSONデータ移行

使い方:
  python scripts/init_db.py              # 初期化 + 移行
  python scripts/init_db.py --reset      # DBリセットして再移行
  python scripts/init_db.py --migrate    # 既存DBにマイグレーション適用
"""

import argparse
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from shelfdb import ShelfDB


def main():
    parser = argparse.ArgumentParser(description="本棚DB初期化")
    parser.add_argument("--reset", action="store_true", help="DBをリセットして再作成")
    parser.add_argument("--migrate", action="store_true", help="既存DBにマイグレーション適用")
    args = parser.parse_args()

    db = ShelfDB()

    if args.migrate:
        if not os.path.exists(db.db_path):
            print(f"DBが見つかりません: {db.db_path}")
            sys.exit(1)
        with db:
            db.init_schema()
            print("マイグレーション完了")
            s = db.stats()
            print(f"  {s['shelves']}棚, {s['items']}アイテム, {s['articles']}記事, {s['rakuten_books']}楽天書籍")
        return

    if args.reset:
        db.reset()
        print(f"DBリセット完了: {db.db_path}")
    elif os.path.exists(db.db_path):
        print(f"DBは既に存在します: {db.db_path}")
        print("リセット: --reset / マイグレーション: --migrate")
        sys.exit(0)
    else:
        db.connect()
        db.init_schema()
        print(f"DB作成: {db.db_path}")

    try:
        print("\nデータ取り込み中...")
        results = db.import_all()

        for key, val in results.items():
            if isinstance(val, dict):
                print(f"  {key}: {val}")
            else:
                print(f"  {key}: {val}件")

        print("\n完了!")
    finally:
        db.close()


if __name__ == "__main__":
    main()
