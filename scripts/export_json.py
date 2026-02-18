#!/usr/bin/env python3
"""
DB → JSON エクスポート

使い方:
  python scripts/export_json.py                    # 全エクスポート
  python scripts/export_json.py --target shelf     # 本棚データのみ
  python scripts/export_json.py --target rakuten   # 楽天書籍のみ
  python scripts/export_json.py --out /path/to/dir # 出力先指定
"""

import argparse
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from shelfdb import ShelfDB


def main():
    parser = argparse.ArgumentParser(description="DB→JSONエクスポート")
    parser.add_argument("--target", choices=["all", "shelf", "rakuten"], default="all")
    parser.add_argument("--out", help="出力ディレクトリ (デフォルト: docs/)")
    args = parser.parse_args()

    with ShelfDB() as db:
        docs = args.out or db.docs_dir

        if args.target in ("all", "shelf"):
            result = db.export_uz_shelf_data(os.path.join(docs, "uz-shelf-data.json"))
            print(f"uz-shelf-data.json: {result['shelves']}棚, {result['items']}アイテム, {result['articles']}記事")

        if args.target in ("all", "rakuten"):
            genre_ids = db.conn.execute(
                "SELECT DISTINCT genre_id FROM rakuten_books ORDER BY genre_id"
            ).fetchall()
            for row in genre_ids:
                gid = row["genre_id"]
                n = db.export_rakuten_json(gid, os.path.join(docs, f"{gid}.json"))
                print(f"{gid}.json: {n}冊")

    print("\n完了!")


if __name__ == "__main__":
    main()
