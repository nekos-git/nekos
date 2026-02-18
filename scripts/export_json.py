#!/usr/bin/env python3
"""
DB → JSON エクスポートスクリプト

SQLiteデータベースからdocs/配下のJSONファイルを生成する。
デプロイ前にこのスクリプトを実行して静的JSONを更新する。

使い方:
  python scripts/export_json.py
"""

import sqlite3
import json
import os

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, "shelf.db")
DOCS_DIR = os.path.join(BASE_DIR, "docs")


def dict_factory(cursor, row):
    return {col[0]: row[i] for i, col in enumerate(cursor.description)}


def export_uz_shelf_data(conn):
    """uz-shelf-data.json を生成"""
    shelves = conn.execute(
        "SELECT id, title, icon FROM shelves ORDER BY sort_order"
    ).fetchall()

    result_shelves = []
    for shelf in shelves:
        items = conn.execute(
            """SELECT item_id, title, full_title, author,
                      cover_url, amazon_url, rakuten_url,
                      article_id, article_title, type, format,
                      width, height
               FROM shelf_items
               WHERE shelf_id = ?
               ORDER BY sort_order""",
            (shelf["id"],),
        ).fetchall()

        result_items = []
        for item in items:
            entry = {
                "id": item["item_id"],
                "title": item["title"],
                "fullTitle": item["full_title"] or "",
                "author": item["author"] or "",
                "coverUrl": item["cover_url"] or "",
                "amazonUrl": item["amazon_url"] or "",
                "rakutenUrl": item["rakuten_url"] or "",
                "articleId": item["article_id"] or "",
                "articleTitle": item["article_title"] or "",
                "type": item["type"] or "product",
                "format": item["format"] or "standard",
                "dimensions": {
                    "width": item["width"] or 128,
                    "height": item["height"] or 182,
                },
            }
            result_items.append(entry)

        result_shelves.append(
            {
                "id": shelf["id"],
                "title": shelf["title"],
                "icon": shelf["icon"] or "",
                "items": result_items,
            }
        )

    # articles
    articles = conn.execute(
        "SELECT id, title, date, categories, shelf, product_count, url FROM articles ORDER BY date DESC"
    ).fetchall()

    result_articles = []
    for a in articles:
        result_articles.append(
            {
                "id": a["id"],
                "title": a["title"],
                "date": a["date"] or "",
                "categories": json.loads(a["categories"]) if a["categories"] else [],
                "shelf": a["shelf"] or "",
                "productCount": a["product_count"] or 0,
                "url": a["url"] or "",
            }
        )

    output = {"shelves": result_shelves, "articles": result_articles}

    path = os.path.join(DOCS_DIR, "uz-shelf-data.json")
    with open(path, "w", encoding="utf-8") as f:
        json.dump(output, f, ensure_ascii=False, indent=2)

    total_items = sum(len(s["items"]) for s in result_shelves)
    print(f"  uz-shelf-data.json: {len(result_shelves)}棚, {total_items}アイテム, {len(result_articles)}記事")


def export_rakuten_books(conn):
    """001005.json, 001006.json, 001010.json を生成"""
    genre_ids = conn.execute(
        "SELECT DISTINCT genre_id FROM rakuten_books ORDER BY genre_id"
    ).fetchall()

    for row in genre_ids:
        genre_id = row["genre_id"]
        books = conn.execute(
            """SELECT isbn, title, author, publisher,
                      item_price, item_url, large_image_url,
                      medium_image_url, small_image_url,
                      item_caption, books_genre_id, sales_date,
                      review_average, review_count, availability
               FROM rakuten_books
               WHERE genre_id = ?
               ORDER BY sort_order""",
            (genre_id,),
        ).fetchall()

        items = []
        for b in books:
            items.append(
                {
                    "Item": {
                        "affiliateUrl": "",
                        "artistName": "",
                        "author": b["author"] or "",
                        "availability": b["availability"] or "",
                        "booksGenreId": b["books_genre_id"] or "",
                        "chirayomiUrl": "",
                        "discountPrice": 0,
                        "discountRate": 0,
                        "hardware": "",
                        "isbn": b["isbn"] or "",
                        "itemCaption": b["item_caption"] or "",
                        "itemPrice": b["item_price"] or 0,
                        "itemUrl": b["item_url"] or "",
                        "jan": "",
                        "label": "",
                        "largeImageUrl": b["large_image_url"] or "",
                        "limitedFlag": 0,
                        "listPrice": 0,
                        "mediumImageUrl": b["medium_image_url"] or "",
                        "os": "",
                        "postageFlag": 2,
                        "publisherName": b["publisher"] or "",
                        "reviewAverage": b["review_average"] or "",
                        "reviewCount": b["review_count"] or 0,
                        "salesDate": b["sales_date"] or "",
                        "smallImageUrl": b["small_image_url"] or "",
                        "title": b["title"],
                    }
                }
            )

        output = {
            "GenreInformation": [],
            "Items": items,
            "carrier": 0,
            "count": len(items),
            "first": 1,
            "hits": len(items),
            "last": len(items),
            "page": 1,
            "pageCount": 1,
        }

        path = os.path.join(DOCS_DIR, f"{genre_id}.json")
        with open(path, "w", encoding="utf-8") as f:
            json.dump(output, f, ensure_ascii=False, indent=2)

        print(f"  {genre_id}.json: {len(items)}冊")


def main():
    if not os.path.exists(DB_PATH):
        print(f"DBが見つかりません: {DB_PATH}")
        print("先に python scripts/init_db.py を実行してください")
        return

    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = dict_factory

    try:
        print("[1] uz-shelf-data.json エクスポート...")
        export_uz_shelf_data(conn)

        print("\n[2] 楽天書籍JSON エクスポート...")
        export_rakuten_books(conn)

        print("\n完了! docs/ 配下のJSONが更新されました")
    finally:
        conn.close()


if __name__ == "__main__":
    main()
