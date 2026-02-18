#!/usr/bin/env python3
"""
本棚DB 管理画面 + REST API

使い方:
  python scripts/admin.py
  → http://localhost:5000 (管理画面)
  → http://localhost:5000/api/ (REST API)

REST API:
  GET    /api/stats                  統計
  GET    /api/items?shelf=books      アイテム一覧
  POST   /api/items                  アイテム追加
  PUT    /api/items/<id>             アイテム更新
  DELETE /api/items/<id>             アイテム削除
  GET    /api/articles               記事一覧
  POST   /api/articles               記事追加
  PUT    /api/articles/<id>          記事更新
  DELETE /api/articles/<id>          記事削除
  GET    /api/rakuten?genre=001005   楽天書籍一覧
  POST   /api/rakuten                楽天書籍追加
  PUT    /api/rakuten/<id>           楽天書籍更新
  DELETE /api/rakuten/<id>           楽天書籍削除
  GET    /api/search?q=...           全文検索
  POST   /api/export                 JSONエクスポート実行
"""

import os
import sys
import re

try:
    from flask import Flask, render_template_string, request, redirect, url_for, flash, jsonify
except ImportError:
    print("Flask が必要です: pip install flask")
    sys.exit(1)

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from shelfdb import ShelfDB

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

app = Flask(__name__)
app.secret_key = "shelf-admin-dev-key"


def get_db():
    return ShelfDB().connect()


# ======================================================================
# REST API
# ======================================================================

@app.route("/api/stats")
def api_stats():
    with ShelfDB() as db:
        return jsonify(db.stats())


# --- Items ---

@app.route("/api/items", methods=["GET"])
def api_items_list():
    with ShelfDB() as db:
        shelf = request.args.get("shelf")
        if shelf:
            rows = db.items.all(where="shelf_id = ?", params=(shelf,))
        else:
            rows = db.items.all(order_by="shelf_id, sort_order")
        return jsonify(db.to_dicts(rows))


@app.route("/api/items", methods=["POST"])
def api_items_create():
    with ShelfDB() as db:
        data = request.get_json()
        db.items.create(**data)
        return jsonify({"status": "created"}), 201


@app.route("/api/items/<int:id>", methods=["PUT"])
def api_items_update(id):
    with ShelfDB() as db:
        data = request.get_json()
        db.items.update(id, **data)
        return jsonify({"status": "updated"})


@app.route("/api/items/<int:id>", methods=["DELETE"])
def api_items_delete(id):
    with ShelfDB() as db:
        db.items.delete(id)
        return jsonify({"status": "deleted"})


# --- Articles ---

@app.route("/api/articles", methods=["GET"])
def api_articles_list():
    with ShelfDB() as db:
        rows = db.articles.all(order_by="date DESC")
        return jsonify(db.to_dicts(rows))


@app.route("/api/articles", methods=["POST"])
def api_articles_create():
    with ShelfDB() as db:
        data = request.get_json()
        db.articles.create(**data)
        return jsonify({"status": "created"}), 201


@app.route("/api/articles/<id>", methods=["PUT"])
def api_articles_update(id):
    with ShelfDB() as db:
        data = request.get_json()
        db.articles.update(id, **data)
        return jsonify({"status": "updated"})


@app.route("/api/articles/<id>", methods=["DELETE"])
def api_articles_delete(id):
    with ShelfDB() as db:
        db.articles.delete(id)
        return jsonify({"status": "deleted"})


# --- Rakuten ---

@app.route("/api/rakuten", methods=["GET"])
def api_rakuten_list():
    with ShelfDB() as db:
        genre = request.args.get("genre")
        if genre:
            rows = db.rakuten.all(where="genre_id = ?", params=(genre,))
        else:
            rows = db.rakuten.all(order_by="genre_id, sort_order")
        return jsonify(db.to_dicts(rows))


@app.route("/api/rakuten", methods=["POST"])
def api_rakuten_create():
    with ShelfDB() as db:
        data = request.get_json()
        db.rakuten.create(**data)
        return jsonify({"status": "created"}), 201


@app.route("/api/rakuten/<int:id>", methods=["PUT"])
def api_rakuten_update(id):
    with ShelfDB() as db:
        data = request.get_json()
        db.rakuten.update(id, **data)
        return jsonify({"status": "updated"})


@app.route("/api/rakuten/<int:id>", methods=["DELETE"])
def api_rakuten_delete(id):
    with ShelfDB() as db:
        db.rakuten.delete(id)
        return jsonify({"status": "deleted"})


# --- Search & Export ---

@app.route("/api/search")
def api_search():
    q = request.args.get("q", "")
    if not q:
        return jsonify({"items": [], "articles": [], "rakuten": []})
    with ShelfDB() as db:
        return jsonify({
            "items": db.to_dicts(db.items.search(q)),
            "articles": db.to_dicts(db.articles.search(q, ["title"])),
            "rakuten": db.to_dicts(db.rakuten.search(q)),
        })


@app.route("/api/export", methods=["POST"])
def api_export():
    with ShelfDB() as db:
        results = db.export_all()
        return jsonify({"status": "exported", "results": _serialize_results(results)})


def _serialize_results(results):
    out = {}
    for k, v in results.items():
        out[k] = v if isinstance(v, (int, str)) else str(v)
    return out


# ======================================================================
# 管理画面 (HTML)
# ======================================================================

ADMIN_HTML = """
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>本棚DB管理</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Hiragino Sans', 'Yu Gothic', sans-serif; background: #f5f5f5; color: #333; }
  .header { background: #2d3436; color: #fff; padding: 16px 24px; display: flex; align-items: center; gap: 16px; }
  .header h1 { font-size: 18px; font-weight: 600; }
  .header .actions { margin-left: auto; display: flex; gap: 8px; }
  .header .btn { background: #00b894; color: #fff; border: none; padding: 8px 16px; border-radius: 6px;
                 cursor: pointer; font-size: 13px; text-decoration: none; }
  .header .btn:hover { background: #00a381; }
  nav { background: #fff; border-bottom: 1px solid #ddd; padding: 0 24px; display: flex; gap: 0; }
  nav a { padding: 12px 20px; text-decoration: none; color: #636e72; font-size: 14px; border-bottom: 3px solid transparent; }
  nav a.active { color: #2d3436; border-bottom-color: #00b894; font-weight: 600; }
  nav a:hover { color: #2d3436; background: #f8f8f8; }
  .container { max-width: 1200px; margin: 24px auto; padding: 0 24px; }
  .flash { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
  .flash.success { background: #d4edda; color: #155724; }
  .flash.error { background: #f8d7da; color: #721c24; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
  th { background: #f8f9fa; text-align: left; padding: 10px 12px; font-size: 12px; color: #636e72; font-weight: 600; }
  td { padding: 10px 12px; border-top: 1px solid #eee; font-size: 13px; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  tr:hover { background: #f8f9fa; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; background: #dfe6e9; }
  .form-card { background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom: 24px; }
  .form-row { display: flex; gap: 16px; margin-bottom: 12px; flex-wrap: wrap; }
  .form-group { flex: 1; min-width: 200px; }
  .form-group label { display: block; font-size: 12px; color: #636e72; margin-bottom: 4px; font-weight: 600; }
  .form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
  .form-group textarea { height: 80px; resize: vertical; }
  .submit-btn { background: #0984e3; color: #fff; border: none; padding: 10px 24px; border-radius: 6px;
                cursor: pointer; font-size: 14px; margin-top: 8px; }
  .submit-btn:hover { background: #0870c4; }
  .delete-btn { background: none; border: none; color: #d63031; cursor: pointer; font-size: 12px; }
  .delete-btn:hover { text-decoration: underline; }
  .edit-btn { background: none; border: none; color: #0984e3; cursor: pointer; font-size: 12px; margin-right: 8px; }
  .edit-btn:hover { text-decoration: underline; }
  .stats { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
  .stat-card { background: #fff; padding: 16px 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); flex: 1; min-width: 140px; }
  .stat-card .num { font-size: 28px; font-weight: 700; color: #2d3436; }
  .stat-card .label { font-size: 12px; color: #636e72; margin-top: 4px; }
  .cover-thumb { width: 40px; height: 56px; object-fit: cover; border-radius: 3px; }
  .search-bar { margin-bottom: 16px; display: flex; gap: 8px; }
  .search-bar input { flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
  .search-bar button { background: #0984e3; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; }
  .ts { font-size: 11px; color: #b2bec3; }
</style>
</head>
<body>

<div class="header">
  <h1>本棚DB管理</h1>
  <div class="actions">
    <a href="{{ url_for('export_view') }}" class="btn" onclick="return confirm('JSONをエクスポートしますか？')">JSONエクスポート</a>
  </div>
</div>

<nav>
  <a href="{{ url_for('index') }}" class="{{ 'active' if active_tab == 'dashboard' }}">ダッシュボード</a>
  <a href="{{ url_for('shelf_items_list') }}" class="{{ 'active' if active_tab == 'items' }}">本棚アイテム</a>
  <a href="{{ url_for('articles_list') }}" class="{{ 'active' if active_tab == 'articles' }}">記事</a>
  <a href="{{ url_for('rakuten_list') }}" class="{{ 'active' if active_tab == 'rakuten' }}">楽天書籍</a>
</nav>

<div class="container">
  {% with messages = get_flashed_messages(with_categories=true) %}
  {% for cat, msg in messages %}
  <div class="flash {{ cat }}">{{ msg }}</div>
  {% endfor %}
  {% endwith %}

  CONTENT_PLACEHOLDER
</div>

</body>
</html>
"""


def render(content_html, active_tab="dashboard", **kwargs):
    full = ADMIN_HTML.replace("CONTENT_PLACEHOLDER", content_html)
    return render_template_string(full, active_tab=active_tab, **kwargs)


# --- Dashboard ---

@app.route("/")
def index():
    with ShelfDB() as db:
        s = db.stats()
        shelf_stats = db.shelf_stats()
    return render("""
<div class="stats">
  <div class="stat-card"><div class="num">{{ s.shelves }}</div><div class="label">棚</div></div>
  <div class="stat-card"><div class="num">{{ s.items }}</div><div class="label">本棚アイテム</div></div>
  <div class="stat-card"><div class="num">{{ s.articles }}</div><div class="label">記事</div></div>
  <div class="stat-card"><div class="num">{{ s.rakuten_books }}</div><div class="label">楽天書籍</div></div>
</div>
<h3 style="margin-bottom:12px">棚ごとのアイテム数</h3>
<table>
  <tr><th>棚ID</th><th>タイトル</th><th>アイテム数</th></tr>
  {% for row in shelf_stats %}
  <tr><td>{{ row.id }}</td><td>{{ row.title }}</td><td><span class="badge">{{ row.count }}</span></td></tr>
  {% endfor %}
</table>
<h3 style="margin:24px 0 12px">REST API</h3>
<table>
  <tr><th>エンドポイント</th><th>説明</th></tr>
  <tr><td>GET /api/stats</td><td>統計情報</td></tr>
  <tr><td>GET/POST /api/items</td><td>本棚アイテム CRUD</td></tr>
  <tr><td>GET/POST /api/articles</td><td>記事 CRUD</td></tr>
  <tr><td>GET/POST /api/rakuten</td><td>楽天書籍 CRUD</td></tr>
  <tr><td>GET /api/search?q=...</td><td>全文検索</td></tr>
  <tr><td>POST /api/export</td><td>JSONエクスポート</td></tr>
</table>
""", active_tab="dashboard", s=s, shelf_stats=shelf_stats)


# --- Shelf Items ---

@app.route("/items")
def shelf_items_list():
    with ShelfDB() as db:
        shelves = db.shelves.all()
        filter_shelf = request.args.get("shelf", "")
        if filter_shelf:
            items = db.items.all(where="shelf_id = ?", params=(filter_shelf,))
        else:
            items = db.items.all(order_by="shelf_id, sort_order")
    return render("""
<h3 style="margin-bottom:16px">本棚アイテム一覧
  <a href="{{ url_for('shelf_item_form') }}" class="submit-btn" style="font-size:12px;padding:6px 12px;margin-left:12px">+ 追加</a>
</h3>
<div style="margin-bottom:12px">
  <form style="display:inline">棚:
    <select name="shelf" onchange="this.form.submit()">
      <option value="">すべて</option>
      {% for s in shelves %}<option value="{{ s.id }}" {{ 'selected' if s.id == filter_shelf }}>{{ s.title }}</option>{% endfor %}
    </select>
  </form>
</div>
<table>
  <tr><th>表紙</th><th>タイトル</th><th>著者</th><th>棚</th><th>記事</th><th>更新日</th><th>操作</th></tr>
  {% for item in items %}
  <tr>
    <td>{% if item.cover_url %}<img class="cover-thumb" src="../docs/{{ item.cover_url }}" onerror="this.style.display='none'">{% endif %}</td>
    <td>{{ item.title }}</td>
    <td>{{ item.author or '-' }}</td>
    <td><span class="badge">{{ item.shelf_id }}</span></td>
    <td>{{ item.article_title or '-' }}</td>
    <td><span class="ts">{{ item.updated_at or '' }}</span></td>
    <td>
      <a href="{{ url_for('shelf_item_form', id=item.id) }}" class="edit-btn">編集</a>
      <form style="display:inline" method="POST" action="{{ url_for('shelf_item_delete_view', id=item.id) }}">
        <button class="delete-btn" onclick="return confirm('削除しますか？')">削除</button>
      </form>
    </td>
  </tr>
  {% endfor %}
</table>
""", active_tab="items", items=items, shelves=shelves, filter_shelf=filter_shelf)


@app.route("/items/form", methods=["GET", "POST"])
def shelf_item_form():
    edit_id = request.args.get("id", type=int)
    with ShelfDB() as db:
        shelves = db.shelves.all()
        if request.method == "POST":
            f = request.form
            fields = dict(
                item_id=f["item_id"], shelf_id=f["shelf_id"], title=f["title"],
                full_title=f.get("full_title", ""), author=f.get("author", ""),
                cover_url=f.get("cover_url", ""), amazon_url=f.get("amazon_url", ""),
                rakuten_url=f.get("rakuten_url", ""), article_id=f.get("article_id", ""),
                article_title=f.get("article_title", ""), format=f.get("format", "standard"),
                width=int(f.get("width") or 128), height=int(f.get("height") or 182),
                sort_order=int(f.get("sort_order") or 0),
            )
            if edit_id:
                db.items.update(edit_id, **fields)
                flash("アイテムを更新しました", "success")
            else:
                fields["type"] = "product"
                db.items.create(**fields)
                flash("アイテムを追加しました", "success")
            return redirect(url_for("shelf_items_list"))
        item = db.items.get(edit_id) if edit_id else None
    return render("""
<h3 style="margin-bottom:16px">{{ '編集' if item else '新規追加' }}: 本棚アイテム</h3>
<div class="form-card">
  <form method="POST">
    <div class="form-row">
      <div class="form-group"><label>アイテムID</label><input name="item_id" value="{{ item.item_id if item else '' }}" required></div>
      <div class="form-group"><label>棚</label><select name="shelf_id" required>{% for s in shelves %}<option value="{{ s.id }}" {{ 'selected' if item and item.shelf_id == s.id }}>{{ s.title }}</option>{% endfor %}</select></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>タイトル</label><input name="title" value="{{ item.title if item else '' }}" required></div>
      <div class="form-group"><label>フルタイトル</label><input name="full_title" value="{{ item.full_title if item else '' }}"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>著者</label><input name="author" value="{{ item.author if item else '' }}"></div>
      <div class="form-group"><label>フォーマット</label><select name="format">{% for f in ['standard','bunko','tankobon','hardcover','shinsho'] %}<option value="{{ f }}" {{ 'selected' if item and item.format == f }}>{{ f }}</option>{% endfor %}</select></div>
    </div>
    <div class="form-row"><div class="form-group"><label>表紙URL</label><input name="cover_url" value="{{ item.cover_url if item else '' }}"></div></div>
    <div class="form-row">
      <div class="form-group"><label>Amazon URL</label><input name="amazon_url" value="{{ item.amazon_url if item else '' }}"></div>
      <div class="form-group"><label>楽天 URL</label><input name="rakuten_url" value="{{ item.rakuten_url if item else '' }}"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>記事ID</label><input name="article_id" value="{{ item.article_id if item else '' }}"></div>
      <div class="form-group"><label>記事タイトル</label><input name="article_title" value="{{ item.article_title if item else '' }}"></div>
    </div>
    <div class="form-row">
      <div class="form-group" style="max-width:120px"><label>幅 (mm)</label><input type="number" name="width" value="{{ item.width if item else 128 }}"></div>
      <div class="form-group" style="max-width:120px"><label>高さ (mm)</label><input type="number" name="height" value="{{ item.height if item else 182 }}"></div>
      <div class="form-group" style="max-width:120px"><label>並び順</label><input type="number" name="sort_order" value="{{ item.sort_order if item else 0 }}"></div>
    </div>
    <button type="submit" class="submit-btn">{{ '更新' if item else '追加' }}</button>
    <a href="{{ url_for('shelf_items_list') }}" style="margin-left:12px;color:#636e72">キャンセル</a>
  </form>
</div>
""", active_tab="items", item=item, shelves=shelves)


@app.route("/items/<int:id>/delete", methods=["POST"])
def shelf_item_delete_view(id):
    with ShelfDB() as db:
        db.items.delete(id)
    flash("アイテムを削除しました", "success")
    return redirect(url_for("shelf_items_list"))


# --- Articles ---

@app.route("/articles")
def articles_list():
    with ShelfDB() as db:
        articles = db.articles.all(order_by="date DESC")
    return render("""
<h3 style="margin-bottom:16px">記事一覧
  <a href="{{ url_for('article_form') }}" class="submit-btn" style="font-size:12px;padding:6px 12px;margin-left:12px">+ 追加</a>
</h3>
<table>
  <tr><th>ID</th><th>タイトル</th><th>日付</th><th>棚</th><th>商品数</th><th>更新日</th><th>操作</th></tr>
  {% for a in articles %}
  <tr>
    <td>{{ a.id }}</td><td>{{ a.title }}</td><td>{{ a.date }}</td>
    <td><span class="badge">{{ a.shelf }}</span></td><td>{{ a.product_count }}</td>
    <td><span class="ts">{{ a.updated_at or '' }}</span></td>
    <td>
      <a href="{{ url_for('article_form', id=a.id) }}" class="edit-btn">編集</a>
      <form style="display:inline" method="POST" action="{{ url_for('article_delete_view', id=a.id) }}">
        <button class="delete-btn" onclick="return confirm('削除しますか？')">削除</button>
      </form>
    </td>
  </tr>
  {% endfor %}
</table>
""", active_tab="articles", articles=articles)


@app.route("/articles/form", methods=["GET", "POST"])
def article_form():
    edit_id = request.args.get("id")
    with ShelfDB() as db:
        shelves = db.shelves.all()
        if request.method == "POST":
            f = request.form
            fields = dict(
                title=f["title"], date=f.get("date", ""),
                shelf=f.get("shelf", ""), product_count=int(f.get("product_count") or 0),
                url=f.get("url", ""), categories="[]",
            )
            if edit_id:
                db.articles.update(edit_id, **fields)
                flash("記事を更新しました", "success")
            else:
                fields["id"] = f["id"]
                db.articles.create(**fields)
                flash("記事を追加しました", "success")
            return redirect(url_for("articles_list"))
        article = db.articles.get(edit_id) if edit_id else None
    return render("""
<h3 style="margin-bottom:16px">{{ '編集' if article else '新規追加' }}: 記事</h3>
<div class="form-card">
  <form method="POST">
    <div class="form-row">
      <div class="form-group"><label>記事ID</label><input name="id" value="{{ article.id if article else '' }}" {{ 'readonly' if article }} required></div>
      <div class="form-group"><label>棚</label><select name="shelf"><option value="">未分類</option>{% for s in shelves %}<option value="{{ s.id }}" {{ 'selected' if article and article.shelf == s.id }}>{{ s.title }}</option>{% endfor %}</select></div>
    </div>
    <div class="form-row">
      <div class="form-group" style="flex:2"><label>タイトル</label><input name="title" value="{{ article.title if article else '' }}" required></div>
      <div class="form-group"><label>日付</label><input name="date" value="{{ article.date if article else '' }}"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>URL</label><input name="url" value="{{ article.url if article else '' }}"></div>
      <div class="form-group" style="max-width:120px"><label>商品数</label><input type="number" name="product_count" value="{{ article.product_count if article else 0 }}"></div>
    </div>
    <button type="submit" class="submit-btn">{{ '更新' if article else '追加' }}</button>
    <a href="{{ url_for('articles_list') }}" style="margin-left:12px;color:#636e72">キャンセル</a>
  </form>
</div>
""", active_tab="articles", article=article, shelves=shelves)


@app.route("/articles/<id>/delete", methods=["POST"])
def article_delete_view(id):
    with ShelfDB() as db:
        db.articles.delete(id)
    flash("記事を削除しました", "success")
    return redirect(url_for("articles_list"))


# --- Rakuten ---

@app.route("/rakuten")
def rakuten_list():
    with ShelfDB() as db:
        filter_genre = request.args.get("genre", "")
        genres = [r["genre_id"] for r in db.conn.execute(
            "SELECT DISTINCT genre_id FROM rakuten_books ORDER BY genre_id"
        ).fetchall()]
        if filter_genre:
            books = db.rakuten.all(where="genre_id = ?", params=(filter_genre,))
        else:
            books = db.rakuten.all(order_by="genre_id, sort_order")
    return render("""
<h3 style="margin-bottom:16px">楽天書籍一覧
  <a href="{{ url_for('rakuten_form') }}" class="submit-btn" style="font-size:12px;padding:6px 12px;margin-left:12px">+ 追加</a>
</h3>
<div style="margin-bottom:12px">
  <form style="display:inline">ジャンル:
    <select name="genre" onchange="this.form.submit()">
      <option value="">すべて</option>
      {% for g in genres %}<option value="{{ g }}" {{ 'selected' if g == filter_genre }}>{{ g }}</option>{% endfor %}
    </select>
  </form>
</div>
<table>
  <tr><th>表紙</th><th>タイトル</th><th>著者</th><th>出版社</th><th>価格</th><th>ジャンル</th><th>更新日</th><th>操作</th></tr>
  {% for b in books %}
  <tr>
    <td>{% if b.large_image_url %}<img class="cover-thumb" src="../docs/{{ b.large_image_url }}" onerror="this.style.display='none'">{% endif %}</td>
    <td>{{ b.title }}</td><td>{{ b.author or '-' }}</td><td>{{ b.publisher or '-' }}</td>
    <td>{{ b.item_price or '-' }}円</td><td><span class="badge">{{ b.genre_id }}</span></td>
    <td><span class="ts">{{ b.updated_at or '' }}</span></td>
    <td>
      <a href="{{ url_for('rakuten_form', id=b.id) }}" class="edit-btn">編集</a>
      <form style="display:inline" method="POST" action="{{ url_for('rakuten_delete_view', id=b.id) }}">
        <button class="delete-btn" onclick="return confirm('削除しますか？')">削除</button>
      </form>
    </td>
  </tr>
  {% endfor %}
</table>
""", active_tab="rakuten", books=books, genres=genres, filter_genre=filter_genre)


@app.route("/rakuten/form", methods=["GET", "POST"])
def rakuten_form():
    edit_id = request.args.get("id", type=int)
    with ShelfDB() as db:
        if request.method == "POST":
            f = request.form
            fields = dict(
                genre_id=f["genre_id"], isbn=f.get("isbn", ""), title=f["title"],
                author=f.get("author", ""), publisher=f.get("publisher", ""),
                item_price=int(f.get("item_price") or 0), item_url=f.get("item_url", ""),
                large_image_url=f.get("large_image_url", ""),
                item_caption=f.get("item_caption", ""), sales_date=f.get("sales_date", ""),
                sort_order=int(f.get("sort_order") or 0),
            )
            if edit_id:
                db.rakuten.update(edit_id, **fields)
                flash("楽天書籍を更新しました", "success")
            else:
                db.rakuten.create(**fields)
                flash("楽天書籍を追加しました", "success")
            return redirect(url_for("rakuten_list"))
        book = db.rakuten.get(edit_id) if edit_id else None
    return render("""
<h3 style="margin-bottom:16px">{{ '編集' if book else '新規追加' }}: 楽天書籍</h3>
<div class="form-card">
  <form method="POST">
    <div class="form-row">
      <div class="form-group"><label>ジャンルID</label><input name="genre_id" value="{{ book.genre_id if book else '001005' }}" required></div>
      <div class="form-group"><label>ISBN</label><input name="isbn" value="{{ book.isbn if book else '' }}"></div>
    </div>
    <div class="form-row">
      <div class="form-group" style="flex:2"><label>タイトル</label><input name="title" value="{{ book.title if book else '' }}" required></div>
      <div class="form-group"><label>著者</label><input name="author" value="{{ book.author if book else '' }}"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>出版社</label><input name="publisher" value="{{ book.publisher if book else '' }}"></div>
      <div class="form-group" style="max-width:150px"><label>価格 (円)</label><input type="number" name="item_price" value="{{ book.item_price if book else '' }}"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>商品URL</label><input name="item_url" value="{{ book.item_url if book else '' }}"></div>
      <div class="form-group"><label>表紙画像URL</label><input name="large_image_url" value="{{ book.large_image_url if book else '' }}"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>発売日</label><input name="sales_date" value="{{ book.sales_date if book else '' }}"></div>
      <div class="form-group" style="max-width:120px"><label>並び順</label><input type="number" name="sort_order" value="{{ book.sort_order if book else 0 }}"></div>
    </div>
    <div class="form-row"><div class="form-group"><label>紹介文</label><textarea name="item_caption">{{ book.item_caption if book else '' }}</textarea></div></div>
    <button type="submit" class="submit-btn">{{ '更新' if book else '追加' }}</button>
    <a href="{{ url_for('rakuten_list') }}" style="margin-left:12px;color:#636e72">キャンセル</a>
  </form>
</div>
""", active_tab="rakuten", book=book)


@app.route("/rakuten/<int:id>/delete", methods=["POST"])
def rakuten_delete_view(id):
    with ShelfDB() as db:
        db.rakuten.delete(id)
    flash("楽天書籍を削除しました", "success")
    return redirect(url_for("rakuten_list"))


# --- Export ---

@app.route("/export")
def export_view():
    with ShelfDB() as db:
        results = db.export_all()
    flash(f"JSONエクスポート完了! {results}", "success")
    return redirect(url_for("index"))


if __name__ == "__main__":
    db = ShelfDB()
    if not os.path.exists(db.db_path):
        print(f"DBが見つかりません: {db.db_path}")
        print("先に python scripts/init_db.py を実行してください")
        sys.exit(1)
    print(f"管理画面: http://localhost:5000")
    print(f"REST API: http://localhost:5000/api/")
    print(f"DB: {db.db_path}")
    app.run(debug=True, port=5000)
