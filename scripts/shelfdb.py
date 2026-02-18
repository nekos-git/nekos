"""
shelfdb - 本棚データベース コアモジュール

すべてのスクリプト（init_db, export_json, admin）から
共通利用されるDB基盤。

使い方:
    from shelfdb import ShelfDB

    with ShelfDB() as db:
        # 棚の一覧
        shelves = db.shelves.all()

        # アイテム追加
        db.items.create(shelf_id="books", title="新しい本", author="著者名")

        # 検索
        results = db.items.search("村上春樹")

        # エクスポート
        db.export_all("/path/to/docs")
"""

import sqlite3
import json
import os
from datetime import datetime

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DEFAULT_DB_PATH = os.path.join(BASE_DIR, "shelf.db")
DEFAULT_DOCS_DIR = os.path.join(BASE_DIR, "docs")

SCHEMA = """
CREATE TABLE IF NOT EXISTS shelves (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    icon TEXT DEFAULT '',
    sort_order INTEGER DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS articles (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    date TEXT DEFAULT '',
    categories TEXT DEFAULT '[]',
    shelf TEXT DEFAULT '' REFERENCES shelves(id),
    product_count INTEGER DEFAULT 0,
    url TEXT DEFAULT '',
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS shelf_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id TEXT NOT NULL,
    shelf_id TEXT NOT NULL REFERENCES shelves(id),
    title TEXT NOT NULL,
    full_title TEXT DEFAULT '',
    author TEXT DEFAULT '',
    cover_url TEXT DEFAULT '',
    amazon_url TEXT DEFAULT '',
    rakuten_url TEXT DEFAULT '',
    article_id TEXT DEFAULT '' REFERENCES articles(id),
    article_title TEXT DEFAULT '',
    type TEXT DEFAULT 'product',
    format TEXT DEFAULT 'standard',
    width INTEGER DEFAULT 128,
    height INTEGER DEFAULT 182,
    sort_order INTEGER DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS rakuten_books (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    genre_id TEXT NOT NULL,
    isbn TEXT DEFAULT '',
    title TEXT NOT NULL,
    author TEXT DEFAULT '',
    publisher TEXT DEFAULT '',
    item_price INTEGER DEFAULT 0,
    item_url TEXT DEFAULT '',
    large_image_url TEXT DEFAULT '',
    medium_image_url TEXT DEFAULT '',
    small_image_url TEXT DEFAULT '',
    item_caption TEXT DEFAULT '',
    books_genre_id TEXT DEFAULT '',
    sales_date TEXT DEFAULT '',
    review_average TEXT DEFAULT '',
    review_count INTEGER DEFAULT 0,
    availability TEXT DEFAULT '',
    affiliate_url TEXT DEFAULT '',
    sort_order INTEGER DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime'))
);

CREATE INDEX IF NOT EXISTS idx_shelf_items_shelf ON shelf_items(shelf_id);
CREATE INDEX IF NOT EXISTS idx_shelf_items_article ON shelf_items(article_id);
CREATE INDEX IF NOT EXISTS idx_articles_shelf ON articles(shelf);
CREATE INDEX IF NOT EXISTS idx_rakuten_books_genre ON rakuten_books(genre_id);
"""

# マイグレーション: 既存DBにタイムスタンプ列を追加
MIGRATIONS = [
    ("shelves", "created_at", "TEXT DEFAULT (datetime('now','localtime'))"),
    ("shelves", "updated_at", "TEXT DEFAULT (datetime('now','localtime'))"),
    ("articles", "created_at", "TEXT DEFAULT (datetime('now','localtime'))"),
    ("articles", "updated_at", "TEXT DEFAULT (datetime('now','localtime'))"),
    ("shelf_items", "created_at", "TEXT DEFAULT (datetime('now','localtime'))"),
    ("shelf_items", "updated_at", "TEXT DEFAULT (datetime('now','localtime'))"),
    ("rakuten_books", "created_at", "TEXT DEFAULT (datetime('now','localtime'))"),
    ("rakuten_books", "updated_at", "TEXT DEFAULT (datetime('now','localtime'))"),
    ("rakuten_books", "affiliate_url", "TEXT DEFAULT ''"),
]


def _now():
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


# ---------------------------------------------------------------------------
# Repository: テーブルごとのCRUD操作を汎用化
# ---------------------------------------------------------------------------

class Repository:
    """汎用CRUDリポジトリ"""

    def __init__(self, conn, table, pk="id"):
        self._conn = conn
        self._table = table
        self._pk = pk

    def all(self, order_by="sort_order", where=None, params=None):
        sql = f"SELECT * FROM {self._table}"
        if where:
            sql += f" WHERE {where}"
        sql += f" ORDER BY {order_by}"
        return self._conn.execute(sql, params or ()).fetchall()

    def get(self, pk_value):
        row = self._conn.execute(
            f"SELECT * FROM {self._table} WHERE {self._pk} = ?", (pk_value,)
        ).fetchone()
        return dict(row) if row else None

    def create(self, **fields):
        fields["created_at"] = _now()
        fields["updated_at"] = _now()
        cols = ", ".join(fields.keys())
        placeholders = ", ".join(["?"] * len(fields))
        self._conn.execute(
            f"INSERT INTO {self._table} ({cols}) VALUES ({placeholders})",
            tuple(fields.values()),
        )
        self._conn.commit()

    def update(self, pk_value, **fields):
        fields["updated_at"] = _now()
        sets = ", ".join(f"{k} = ?" for k in fields.keys())
        self._conn.execute(
            f"UPDATE {self._table} SET {sets} WHERE {self._pk} = ?",
            (*fields.values(), pk_value),
        )
        self._conn.commit()

    def delete(self, pk_value):
        self._conn.execute(
            f"DELETE FROM {self._table} WHERE {self._pk} = ?", (pk_value,)
        )
        self._conn.commit()

    def count(self, where=None, params=None):
        sql = f"SELECT COUNT(*) FROM {self._table}"
        if where:
            sql += f" WHERE {where}"
        return self._conn.execute(sql, params or ()).fetchone()[0]

    def search(self, query, columns=None):
        if columns is None:
            columns = ["title", "author"]
        clauses = " OR ".join(f"{c} LIKE ?" for c in columns)
        params = [f"%{query}%"] * len(columns)
        return self._conn.execute(
            f"SELECT * FROM {self._table} WHERE {clauses} ORDER BY sort_order",
            params,
        ).fetchall()

    def upsert(self, pk_value, **fields):
        existing = self.get(pk_value)
        if existing:
            self.update(pk_value, **fields)
        else:
            fields[self._pk] = pk_value
            self.create(**fields)

    def bulk_create(self, rows):
        if not rows:
            return
        fields = list(rows[0].keys())
        if "created_at" not in fields:
            fields += ["created_at", "updated_at"]
        cols = ", ".join(fields)
        placeholders = ", ".join(["?"] * len(fields))
        now = _now()
        values = []
        for row in rows:
            row_values = [row.get(f, "") for f in list(rows[0].keys())]
            if "created_at" not in rows[0]:
                row_values += [now, now]
            values.append(tuple(row_values))
        self._conn.executemany(
            f"INSERT INTO {self._table} ({cols}) VALUES ({placeholders})",
            values,
        )
        self._conn.commit()


# ---------------------------------------------------------------------------
# ShelfDB: メインクラス
# ---------------------------------------------------------------------------

class ShelfDB:
    """
    本棚データベース

    with ShelfDB() as db:
        db.shelves.all()
        db.items.create(...)
    """

    def __init__(self, db_path=None, docs_dir=None):
        self.db_path = db_path or DEFAULT_DB_PATH
        self.docs_dir = docs_dir or DEFAULT_DOCS_DIR
        self._conn = None

    def __enter__(self):
        self.connect()
        return self

    def __exit__(self, exc_type, exc_val, exc_tb):
        self.close()

    def connect(self):
        self._conn = sqlite3.connect(self.db_path)
        self._conn.row_factory = sqlite3.Row
        self._conn.execute("PRAGMA journal_mode=WAL")
        self._conn.execute("PRAGMA foreign_keys=ON")
        self._init_repos()
        return self

    def close(self):
        if self._conn:
            self._conn.close()
            self._conn = None

    @property
    def conn(self):
        return self._conn

    def _init_repos(self):
        self.shelves = Repository(self._conn, "shelves")
        self.items = Repository(self._conn, "shelf_items")
        self.articles = Repository(self._conn, "articles")
        self.rakuten = Repository(self._conn, "rakuten_books")

    # --- スキーマ管理 ---

    def init_schema(self):
        self._conn.executescript(SCHEMA)
        self._run_migrations()

    def _run_migrations(self):
        for table, column, definition in MIGRATIONS:
            try:
                self._conn.execute(
                    f"ALTER TABLE {table} ADD COLUMN {column} {definition}"
                )
            except sqlite3.OperationalError:
                pass  # 列が既に存在する
        self._conn.commit()

    def reset(self):
        self.close()
        if os.path.exists(self.db_path):
            os.remove(self.db_path)
        self.connect()
        self.init_schema()

    # --- インポート ---

    def _fk_off(self):
        self._conn.execute("PRAGMA foreign_keys=OFF")

    def _fk_on(self):
        self._conn.execute("PRAGMA foreign_keys=ON")

    def import_uz_shelf_data(self, path=None):
        path = path or os.path.join(self.docs_dir, "uz-shelf-data.json")
        with open(path, "r", encoding="utf-8") as f:
            data = json.load(f)

        for i, shelf in enumerate(data.get("shelves", [])):
            self.shelves.upsert(
                shelf["id"],
                title=shelf["title"],
                icon=shelf.get("icon", ""),
                sort_order=i,
            )

        for shelf in data.get("shelves", []):
            for i, item in enumerate(shelf.get("items", [])):
                dims = item.get("dimensions", {})
                self.items.create(
                    item_id=item.get("id", ""),
                    shelf_id=shelf["id"],
                    title=item.get("title", ""),
                    full_title=item.get("fullTitle", ""),
                    author=item.get("author", ""),
                    cover_url=item.get("coverUrl", ""),
                    amazon_url=item.get("amazonUrl", ""),
                    rakuten_url=item.get("rakutenUrl", ""),
                    article_id=item.get("articleId", ""),
                    article_title=item.get("articleTitle", ""),
                    type=item.get("type", "product"),
                    format=item.get("format", "standard"),
                    width=dims.get("width", 128),
                    height=dims.get("height", 182),
                    sort_order=i,
                )

        for article in data.get("articles", []):
            self.articles.upsert(
                article["id"],
                title=article["title"],
                date=article.get("date", ""),
                categories=json.dumps(article.get("categories", []), ensure_ascii=False),
                shelf=article.get("shelf", ""),
                product_count=article.get("productCount", 0),
                url=article.get("url", ""),
            )

        return {
            "shelves": self.shelves.count(),
            "items": self.items.count(),
            "articles": self.articles.count(),
        }

    def import_rakuten_json(self, path, genre_id):
        with open(path, "r", encoding="utf-8") as f:
            data = json.load(f)

        count = 0
        for i, entry in enumerate(data.get("Items", [])):
            item = entry.get("Item", {})
            self.rakuten.create(
                genre_id=genre_id,
                isbn=item.get("isbn", ""),
                title=item.get("title", ""),
                author=item.get("author", ""),
                publisher=item.get("publisherName", ""),
                item_price=item.get("itemPrice", 0),
                item_url=item.get("itemUrl", ""),
                large_image_url=item.get("largeImageUrl", ""),
                medium_image_url=item.get("mediumImageUrl", ""),
                small_image_url=item.get("smallImageUrl", ""),
                item_caption=item.get("itemCaption", ""),
                books_genre_id=item.get("booksGenreId", ""),
                sales_date=item.get("salesDate", ""),
                review_average=item.get("reviewAverage", ""),
                review_count=item.get("reviewCount", 0),
                availability=item.get("availability", ""),
                affiliate_url=item.get("affiliateUrl", ""),
                sort_order=i,
            )
            count += 1
        return count

    def import_all(self, docs_dir=None):
        docs = docs_dir or self.docs_dir
        self._fk_off()
        results = {}

        uz_path = os.path.join(docs, "uz-shelf-data.json")
        if os.path.exists(uz_path):
            results["uz_shelf"] = self.import_uz_shelf_data(uz_path)

        genre_labels = {"001005": "IT", "001006": "ビジネス", "001010": "文化"}
        for genre_id, label in genre_labels.items():
            path = os.path.join(docs, f"{genre_id}.json")
            if os.path.exists(path):
                n = self.import_rakuten_json(path, genre_id)
                results[f"rakuten_{genre_id}"] = n

        self._fk_on()
        return results

    # --- エクスポート ---

    def export_uz_shelf_data(self, output_path=None):
        output_path = output_path or os.path.join(self.docs_dir, "uz-shelf-data.json")
        shelves = self.shelves.all()

        result_shelves = []
        for shelf in shelves:
            items = self.items.all(where="shelf_id = ?", params=(shelf["id"],))
            result_items = [
                {
                    "id": it["item_id"],
                    "title": it["title"],
                    "fullTitle": it["full_title"] or "",
                    "author": it["author"] or "",
                    "coverUrl": it["cover_url"] or "",
                    "amazonUrl": it["amazon_url"] or "",
                    "rakutenUrl": it["rakuten_url"] or "",
                    "articleId": it["article_id"] or "",
                    "articleTitle": it["article_title"] or "",
                    "type": it["type"] or "product",
                    "format": it["format"] or "standard",
                    "dimensions": {
                        "width": it["width"] or 128,
                        "height": it["height"] or 182,
                    },
                }
                for it in items
            ]
            result_shelves.append({
                "id": shelf["id"],
                "title": shelf["title"],
                "icon": shelf["icon"] or "",
                "items": result_items,
            })

        articles = self.articles.all(order_by="date DESC")
        result_articles = [
            {
                "id": a["id"],
                "title": a["title"],
                "date": a["date"] or "",
                "categories": json.loads(a["categories"]) if a["categories"] else [],
                "shelf": a["shelf"] or "",
                "productCount": a["product_count"] or 0,
                "url": a["url"] or "",
            }
            for a in articles
        ]

        output = {"shelves": result_shelves, "articles": result_articles}
        with open(output_path, "w", encoding="utf-8") as f:
            json.dump(output, f, ensure_ascii=False, indent=2)

        total_items = sum(len(s["items"]) for s in result_shelves)
        return {"shelves": len(result_shelves), "items": total_items, "articles": len(result_articles)}

    def export_rakuten_json(self, genre_id, output_path=None):
        output_path = output_path or os.path.join(self.docs_dir, f"{genre_id}.json")
        books = self.rakuten.all(where="genre_id = ?", params=(genre_id,))

        items = [
            {
                "Item": {
                    "affiliateUrl": b["affiliate_url"] or "", "artistName": "",
                    "author": b["author"] or "",
                    "availability": b["availability"] or "",
                    "booksGenreId": b["books_genre_id"] or "",
                    "chirayomiUrl": "",
                    "discountPrice": 0, "discountRate": 0, "hardware": "",
                    "isbn": b["isbn"] or "",
                    "itemCaption": b["item_caption"] or "",
                    "itemPrice": b["item_price"] or 0,
                    "itemUrl": b["item_url"] or "",
                    "jan": "", "label": "",
                    "largeImageUrl": b["large_image_url"] or "",
                    "limitedFlag": 0, "listPrice": 0,
                    "mediumImageUrl": b["medium_image_url"] or "",
                    "os": "", "postageFlag": 2,
                    "publisherName": b["publisher"] or "",
                    "reviewAverage": b["review_average"] or "",
                    "reviewCount": b["review_count"] or 0,
                    "salesDate": b["sales_date"] or "",
                    "smallImageUrl": b["small_image_url"] or "",
                    "title": b["title"],
                }
            }
            for b in books
        ]

        output = {
            "GenreInformation": [], "Items": items,
            "carrier": 0, "count": len(items), "first": 1,
            "hits": len(items), "last": len(items), "page": 1, "pageCount": 1,
        }

        with open(output_path, "w", encoding="utf-8") as f:
            json.dump(output, f, ensure_ascii=False, indent=2)
        return len(items)

    def export_all(self, docs_dir=None):
        docs = docs_dir or self.docs_dir
        results = {}

        results["uz_shelf"] = self.export_uz_shelf_data(
            os.path.join(docs, "uz-shelf-data.json")
        )

        genre_ids = self._conn.execute(
            "SELECT DISTINCT genre_id FROM rakuten_books ORDER BY genre_id"
        ).fetchall()
        for row in genre_ids:
            gid = row["genre_id"]
            n = self.export_rakuten_json(gid, os.path.join(docs, f"{gid}.json"))
            results[f"rakuten_{gid}"] = n

        return results

    # --- 統計・ユーティリティ ---

    def stats(self):
        return {
            "shelves": self.shelves.count(),
            "items": self.items.count(),
            "articles": self.articles.count(),
            "rakuten_books": self.rakuten.count(),
        }

    def shelf_stats(self):
        return self._conn.execute(
            """SELECT s.id, s.title, COUNT(si.id) as count
               FROM shelves s LEFT JOIN shelf_items si ON s.id = si.shelf_id
               GROUP BY s.id ORDER BY s.sort_order"""
        ).fetchall()

    def to_dict(self, row):
        """sqlite3.Row → dict"""
        return dict(row) if row else None

    def to_dicts(self, rows):
        """sqlite3.Row のリスト → dict のリスト"""
        return [dict(r) for r in rows]
