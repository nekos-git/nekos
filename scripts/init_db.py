#!/usr/bin/env python3
"""
SQLite DB初期化 + 既存JSONデータ移行スクリプト

既存のJSON (uz-shelf-data.json, 001005.json等) を
SQLiteデータベースに取り込む。

使い方:
  python scripts/init_db.py              # 初期化 + 移行
  python scripts/init_db.py --reset      # DBリセットして再移行
"""

import sqlite3
import json
import os
import sys
import argparse

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, "shelf.db")
DOCS_DIR = os.path.join(BASE_DIR, "docs")

SCHEMA = """
CREATE TABLE IF NOT EXISTS shelves (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    icon TEXT,
    sort_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS articles (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    date TEXT,
    categories TEXT,  -- JSON array
    shelf TEXT REFERENCES shelves(id),
    product_count INTEGER DEFAULT 0,
    url TEXT
);

CREATE TABLE IF NOT EXISTS shelf_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id TEXT NOT NULL,
    shelf_id TEXT NOT NULL REFERENCES shelves(id),
    title TEXT NOT NULL,
    full_title TEXT,
    author TEXT,
    cover_url TEXT,
    amazon_url TEXT,
    rakuten_url TEXT,
    article_id TEXT REFERENCES articles(id),
    article_title TEXT,
    type TEXT DEFAULT 'product',
    format TEXT DEFAULT 'standard',
    width INTEGER,
    height INTEGER,
    sort_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS rakuten_books (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    genre_id TEXT NOT NULL,      -- 001005, 001006, 001010
    isbn TEXT,
    title TEXT NOT NULL,
    author TEXT,
    publisher TEXT,
    item_price INTEGER,
    item_url TEXT,
    large_image_url TEXT,
    medium_image_url TEXT,
    small_image_url TEXT,
    item_caption TEXT,
    books_genre_id TEXT,
    sales_date TEXT,
    review_average TEXT,
    review_count INTEGER,
    availability TEXT,
    sort_order INTEGER DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_shelf_items_shelf ON shelf_items(shelf_id);
CREATE INDEX IF NOT EXISTS idx_shelf_items_article ON shelf_items(article_id);
CREATE INDEX IF NOT EXISTS idx_articles_shelf ON articles(shelf);
CREATE INDEX IF NOT EXISTS idx_rakuten_books_genre ON rakuten_books(genre_id);
"""


def init_schema(conn):
    conn.executescript(SCHEMA)
    print("  テーブル作成完了")


def import_uz_shelf_data(conn):
    path = os.path.join(DOCS_DIR, "uz-shelf-data.json")
    if not os.path.exists(path):
        print(f"  スキップ: {path} が見つかりません")
        return

    with open(path, "r", encoding="utf-8") as f:
        data = json.load(f)

    # shelves
    for i, shelf in enumerate(data.get("shelves", [])):
        conn.execute(
            "INSERT OR REPLACE INTO shelves (id, title, icon, sort_order) VALUES (?, ?, ?, ?)",
            (shelf["id"], shelf["title"], shelf.get("icon", ""), i),
        )

    # shelf items
    for shelf in data.get("shelves", []):
        for i, item in enumerate(shelf.get("items", [])):
            dims = item.get("dimensions", {})
            conn.execute(
                """INSERT INTO shelf_items
                   (item_id, shelf_id, title, full_title, author,
                    cover_url, amazon_url, rakuten_url,
                    article_id, article_title, type, format,
                    width, height, sort_order)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
                (
                    item.get("id", ""),
                    shelf["id"],
                    item.get("title", ""),
                    item.get("fullTitle", ""),
                    item.get("author", ""),
                    item.get("coverUrl", ""),
                    item.get("amazonUrl", ""),
                    item.get("rakutenUrl", ""),
                    item.get("articleId", ""),
                    item.get("articleTitle", ""),
                    item.get("type", "product"),
                    item.get("format", "standard"),
                    dims.get("width"),
                    dims.get("height"),
                    i,
                ),
            )

    # articles
    for article in data.get("articles", []):
        conn.execute(
            """INSERT OR REPLACE INTO articles
               (id, title, date, categories, shelf, product_count, url)
               VALUES (?, ?, ?, ?, ?, ?, ?)""",
            (
                article["id"],
                article["title"],
                article.get("date", ""),
                json.dumps(article.get("categories", []), ensure_ascii=False),
                article.get("shelf", ""),
                article.get("productCount", 0),
                article.get("url", ""),
            ),
        )

    shelf_count = conn.execute("SELECT COUNT(*) FROM shelves").fetchone()[0]
    item_count = conn.execute("SELECT COUNT(*) FROM shelf_items").fetchone()[0]
    article_count = conn.execute("SELECT COUNT(*) FROM articles").fetchone()[0]
    print(f"  uz-shelf-data: {shelf_count}棚, {item_count}アイテム, {article_count}記事")


def import_rakuten_books(conn):
    genre_files = {"001005": "IT・テクノロジー", "001006": "ビジネス・経済", "001010": "文化・生活"}

    total = 0
    for genre_id, label in genre_files.items():
        path = os.path.join(DOCS_DIR, f"{genre_id}.json")
        if not os.path.exists(path):
            print(f"  スキップ: {path}")
            continue

        with open(path, "r", encoding="utf-8") as f:
            data = json.load(f)

        for i, entry in enumerate(data.get("Items", [])):
            item = entry.get("Item", {})
            conn.execute(
                """INSERT INTO rakuten_books
                   (genre_id, isbn, title, author, publisher,
                    item_price, item_url, large_image_url,
                    medium_image_url, small_image_url,
                    item_caption, books_genre_id, sales_date,
                    review_average, review_count, availability, sort_order)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
                (
                    genre_id,
                    item.get("isbn", ""),
                    item.get("title", ""),
                    item.get("author", ""),
                    item.get("publisherName", ""),
                    item.get("itemPrice"),
                    item.get("itemUrl", ""),
                    item.get("largeImageUrl", ""),
                    item.get("mediumImageUrl", ""),
                    item.get("smallImageUrl", ""),
                    item.get("itemCaption", ""),
                    item.get("booksGenreId", ""),
                    item.get("salesDate", ""),
                    item.get("reviewAverage", ""),
                    item.get("reviewCount", 0),
                    item.get("availability", ""),
                    i,
                ),
            )
            total += 1

        print(f"  楽天 {genre_id} ({label}): {len(data.get('Items', []))}冊")

    print(f"  楽天書籍合計: {total}冊")


def main():
    parser = argparse.ArgumentParser(description="本棚DB初期化")
    parser.add_argument("--reset", action="store_true", help="DBをリセットして再作成")
    args = parser.parse_args()

    if args.reset and os.path.exists(DB_PATH):
        os.remove(DB_PATH)
        print(f"既存DB削除: {DB_PATH}")

    if os.path.exists(DB_PATH) and not args.reset:
        print(f"DBは既に存在します: {DB_PATH}")
        print("リセットするには --reset オプションを付けてください")
        sys.exit(0)

    print(f"DB作成: {DB_PATH}")
    conn = sqlite3.connect(DB_PATH)

    try:
        print("\n[1] スキーマ作成...")
        init_schema(conn)

        print("\n[2] uz-shelf-data.json 取り込み...")
        import_uz_shelf_data(conn)

        print("\n[3] 楽天API書籍データ取り込み...")
        import_rakuten_books(conn)

        conn.commit()
        print("\n完了!")
    except Exception as e:
        conn.rollback()
        print(f"\nエラー: {e}")
        sys.exit(1)
    finally:
        conn.close()


if __name__ == "__main__":
    main()
