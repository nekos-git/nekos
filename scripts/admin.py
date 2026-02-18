#!/usr/bin/env python3
"""
本棚DB 簡易Web管理画面

使い方:
  pip install flask
  python scripts/admin.py
  → http://localhost:5000 でアクセス

機能:
  - 棚・アイテム・記事・楽天書籍の一覧表示
  - 各データの追加・編集・削除
  - DB → JSON エクスポート（ワンクリック）
"""

import sqlite3
import json
import os
import subprocess
import sys

try:
    from flask import Flask, render_template_string, request, redirect, url_for, flash, jsonify
except ImportError:
    print("Flask が必要です: pip install flask")
    sys.exit(1)

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, "shelf.db")

app = Flask(__name__)
app.secret_key = "shelf-admin-dev-key"


def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


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
  .header .btn.danger { background: #d63031; }
  .header .btn.danger:hover { background: #c0392b; }
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
</style>
</head>
<body>

<div class="header">
  <h1>本棚DB管理</h1>
  <div class="actions">
    <a href="{{ url_for('export_json') }}" class="btn" onclick="return confirm('JSONをエクスポートしますか？')">JSONエクスポート</a>
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

  {% block content %}{% endblock %}
</div>

</body>
</html>
"""

DASHBOARD_HTML = """
{% extends "base" %}
{% block content %}
<div class="stats">
  <div class="stat-card"><div class="num">{{ shelf_count }}</div><div class="label">棚</div></div>
  <div class="stat-card"><div class="num">{{ item_count }}</div><div class="label">本棚アイテム</div></div>
  <div class="stat-card"><div class="num">{{ article_count }}</div><div class="label">記事</div></div>
  <div class="stat-card"><div class="num">{{ rakuten_count }}</div><div class="label">楽天書籍</div></div>
</div>

<h3 style="margin-bottom: 12px;">棚ごとのアイテム数</h3>
<table>
  <tr><th>棚ID</th><th>タイトル</th><th>アイテム数</th></tr>
  {% for s in shelf_stats %}
  <tr><td>{{ s.id }}</td><td>{{ s.title }}</td><td><span class="badge">{{ s.count }}</span></td></tr>
  {% endfor %}
</table>
{% endblock %}
"""

ITEMS_HTML = """
{% extends "base" %}
{% block content %}
<h3 style="margin-bottom: 16px;">本棚アイテム一覧
  <a href="{{ url_for('shelf_item_add') }}" class="submit-btn" style="font-size:12px; padding:6px 12px; margin-left:12px;">+ 追加</a>
</h3>

<div style="margin-bottom: 12px;">
  <form style="display:inline">
    棚:
    <select name="shelf" onchange="this.form.submit()">
      <option value="">すべて</option>
      {% for s in shelves %}
      <option value="{{ s.id }}" {{ 'selected' if s.id == filter_shelf }}>{{ s.title }}</option>
      {% endfor %}
    </select>
  </form>
</div>

<table>
  <tr><th>表紙</th><th>タイトル</th><th>著者</th><th>棚</th><th>記事</th><th>操作</th></tr>
  {% for item in items %}
  <tr>
    <td>{% if item.cover_url %}<img class="cover-thumb" src="../docs/{{ item.cover_url }}" onerror="this.style.display='none'">{% endif %}</td>
    <td>{{ item.title }}</td>
    <td>{{ item.author or '-' }}</td>
    <td><span class="badge">{{ item.shelf_id }}</span></td>
    <td>{{ item.article_title or '-' }}</td>
    <td>
      <a href="{{ url_for('shelf_item_edit', id=item.id) }}" class="edit-btn">編集</a>
      <form style="display:inline" method="POST" action="{{ url_for('shelf_item_delete', id=item.id) }}">
        <button class="delete-btn" onclick="return confirm('削除しますか？')">削除</button>
      </form>
    </td>
  </tr>
  {% endfor %}
</table>
{% endblock %}
"""

ITEM_FORM_HTML = """
{% extends "base" %}
{% block content %}
<h3 style="margin-bottom: 16px;">{{ '編集' if item else '新規追加' }}: 本棚アイテム</h3>
<div class="form-card">
  <form method="POST">
    <div class="form-row">
      <div class="form-group">
        <label>アイテムID</label>
        <input name="item_id" value="{{ item.item_id if item else '' }}" required>
      </div>
      <div class="form-group">
        <label>棚</label>
        <select name="shelf_id" required>
          {% for s in shelves %}
          <option value="{{ s.id }}" {{ 'selected' if item and item.shelf_id == s.id }}>{{ s.title }}</option>
          {% endfor %}
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>タイトル</label>
        <input name="title" value="{{ item.title if item else '' }}" required>
      </div>
      <div class="form-group">
        <label>フルタイトル</label>
        <input name="full_title" value="{{ item.full_title if item else '' }}">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>著者</label>
        <input name="author" value="{{ item.author if item else '' }}">
      </div>
      <div class="form-group">
        <label>フォーマット</label>
        <select name="format">
          {% for f in ['standard','bunko','tankobon','hardcover','shinsho'] %}
          <option value="{{ f }}" {{ 'selected' if item and item.format == f }}>{{ f }}</option>
          {% endfor %}
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>表紙URL</label>
        <input name="cover_url" value="{{ item.cover_url if item else '' }}">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Amazon URL</label>
        <input name="amazon_url" value="{{ item.amazon_url if item else '' }}">
      </div>
      <div class="form-group">
        <label>楽天 URL</label>
        <input name="rakuten_url" value="{{ item.rakuten_url if item else '' }}">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>記事ID</label>
        <input name="article_id" value="{{ item.article_id if item else '' }}">
      </div>
      <div class="form-group">
        <label>記事タイトル</label>
        <input name="article_title" value="{{ item.article_title if item else '' }}">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group" style="max-width:120px">
        <label>幅 (mm)</label>
        <input type="number" name="width" value="{{ item.width if item and item.width else 128 }}">
      </div>
      <div class="form-group" style="max-width:120px">
        <label>高さ (mm)</label>
        <input type="number" name="height" value="{{ item.height if item and item.height else 182 }}">
      </div>
      <div class="form-group" style="max-width:120px">
        <label>並び順</label>
        <input type="number" name="sort_order" value="{{ item.sort_order if item else 0 }}">
      </div>
    </div>
    <button type="submit" class="submit-btn">{{ '更新' if item else '追加' }}</button>
    <a href="{{ url_for('shelf_items_list') }}" style="margin-left:12px; color:#636e72;">キャンセル</a>
  </form>
</div>
{% endblock %}
"""

ARTICLES_HTML = """
{% extends "base" %}
{% block content %}
<h3 style="margin-bottom: 16px;">記事一覧
  <a href="{{ url_for('article_add') }}" class="submit-btn" style="font-size:12px; padding:6px 12px; margin-left:12px;">+ 追加</a>
</h3>
<table>
  <tr><th>ID</th><th>タイトル</th><th>日付</th><th>棚</th><th>商品数</th><th>操作</th></tr>
  {% for a in articles %}
  <tr>
    <td>{{ a.id }}</td>
    <td>{{ a.title }}</td>
    <td>{{ a.date }}</td>
    <td><span class="badge">{{ a.shelf }}</span></td>
    <td>{{ a.product_count }}</td>
    <td>
      <a href="{{ url_for('article_edit', id=a.id) }}" class="edit-btn">編集</a>
      <form style="display:inline" method="POST" action="{{ url_for('article_delete', id=a.id) }}">
        <button class="delete-btn" onclick="return confirm('削除しますか？')">削除</button>
      </form>
    </td>
  </tr>
  {% endfor %}
</table>
{% endblock %}
"""

ARTICLE_FORM_HTML = """
{% extends "base" %}
{% block content %}
<h3 style="margin-bottom: 16px;">{{ '編集' if article else '新規追加' }}: 記事</h3>
<div class="form-card">
  <form method="POST">
    <div class="form-row">
      <div class="form-group">
        <label>記事ID</label>
        <input name="id" value="{{ article.id if article else '' }}" {{ 'readonly' if article }} required>
      </div>
      <div class="form-group">
        <label>棚</label>
        <select name="shelf">
          <option value="">未分類</option>
          {% for s in shelves %}
          <option value="{{ s.id }}" {{ 'selected' if article and article.shelf == s.id }}>{{ s.title }}</option>
          {% endfor %}
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group" style="flex:2">
        <label>タイトル</label>
        <input name="title" value="{{ article.title if article else '' }}" required>
      </div>
      <div class="form-group">
        <label>日付</label>
        <input name="date" value="{{ article.date if article else '' }}">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>URL</label>
        <input name="url" value="{{ article.url if article else '' }}">
      </div>
      <div class="form-group" style="max-width:120px">
        <label>商品数</label>
        <input type="number" name="product_count" value="{{ article.product_count if article else 0 }}">
      </div>
    </div>
    <button type="submit" class="submit-btn">{{ '更新' if article else '追加' }}</button>
    <a href="{{ url_for('articles_list') }}" style="margin-left:12px; color:#636e72;">キャンセル</a>
  </form>
</div>
{% endblock %}
"""

RAKUTEN_HTML = """
{% extends "base" %}
{% block content %}
<h3 style="margin-bottom: 16px;">楽天書籍一覧
  <a href="{{ url_for('rakuten_add') }}" class="submit-btn" style="font-size:12px; padding:6px 12px; margin-left:12px;">+ 追加</a>
</h3>

<div style="margin-bottom: 12px;">
  <form style="display:inline">
    ジャンル:
    <select name="genre" onchange="this.form.submit()">
      <option value="">すべて</option>
      {% for g in genres %}
      <option value="{{ g }}" {{ 'selected' if g == filter_genre }}>{{ g }}</option>
      {% endfor %}
    </select>
  </form>
</div>

<table>
  <tr><th>表紙</th><th>タイトル</th><th>著者</th><th>出版社</th><th>価格</th><th>ジャンル</th><th>操作</th></tr>
  {% for b in books %}
  <tr>
    <td>{% if b.large_image_url %}<img class="cover-thumb" src="../docs/{{ b.large_image_url }}" onerror="this.style.display='none'">{% endif %}</td>
    <td>{{ b.title }}</td>
    <td>{{ b.author or '-' }}</td>
    <td>{{ b.publisher or '-' }}</td>
    <td>{{ b.item_price or '-' }}円</td>
    <td><span class="badge">{{ b.genre_id }}</span></td>
    <td>
      <a href="{{ url_for('rakuten_edit', id=b.id) }}" class="edit-btn">編集</a>
      <form style="display:inline" method="POST" action="{{ url_for('rakuten_delete', id=b.id) }}">
        <button class="delete-btn" onclick="return confirm('削除しますか？')">削除</button>
      </form>
    </td>
  </tr>
  {% endfor %}
</table>
{% endblock %}
"""

RAKUTEN_FORM_HTML = """
{% extends "base" %}
{% block content %}
<h3 style="margin-bottom: 16px;">{{ '編集' if book else '新規追加' }}: 楽天書籍</h3>
<div class="form-card">
  <form method="POST">
    <div class="form-row">
      <div class="form-group">
        <label>ジャンルID (001005/001006/001010)</label>
        <input name="genre_id" value="{{ book.genre_id if book else '001005' }}" required>
      </div>
      <div class="form-group">
        <label>ISBN</label>
        <input name="isbn" value="{{ book.isbn if book else '' }}">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group" style="flex:2">
        <label>タイトル</label>
        <input name="title" value="{{ book.title if book else '' }}" required>
      </div>
      <div class="form-group">
        <label>著者</label>
        <input name="author" value="{{ book.author if book else '' }}">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>出版社</label>
        <input name="publisher" value="{{ book.publisher if book else '' }}">
      </div>
      <div class="form-group" style="max-width:150px">
        <label>価格 (円)</label>
        <input type="number" name="item_price" value="{{ book.item_price if book else '' }}">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>商品URL</label>
        <input name="item_url" value="{{ book.item_url if book else '' }}">
      </div>
      <div class="form-group">
        <label>表紙画像URL (large)</label>
        <input name="large_image_url" value="{{ book.large_image_url if book else '' }}">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>発売日</label>
        <input name="sales_date" value="{{ book.sales_date if book else '' }}">
      </div>
      <div class="form-group" style="max-width:120px">
        <label>並び順</label>
        <input type="number" name="sort_order" value="{{ book.sort_order if book else 0 }}">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>紹介文</label>
        <textarea name="item_caption">{{ book.item_caption if book else '' }}</textarea>
      </div>
    </div>
    <button type="submit" class="submit-btn">{{ '更新' if book else '追加' }}</button>
    <a href="{{ url_for('rakuten_list') }}" style="margin-left:12px; color:#636e72;">キャンセル</a>
  </form>
</div>
{% endblock %}
"""

# Template rendering helper
templates = {
    "base": ADMIN_HTML,
    "dashboard": DASHBOARD_HTML,
    "items": ITEMS_HTML,
    "item_form": ITEM_FORM_HTML,
    "articles": ARTICLES_HTML,
    "article_form": ARTICLE_FORM_HTML,
    "rakuten": RAKUTEN_HTML,
    "rakuten_form": RAKUTEN_FORM_HTML,
}


def render(template_name, **kwargs):
    # Jinja2 doesn't support extends with render_template_string easily,
    # so we manually compose base + content
    base = ADMIN_HTML
    content_template = templates[template_name]
    # Extract block content
    import re
    match = re.search(r'\{%\s*block content\s*%\}(.*?)\{%\s*endblock\s*%\}', content_template, re.DOTALL)
    if match:
        content = match.group(1)
    else:
        content = content_template
    full = base.replace("{% block content %}{% endblock %}", content)
    return render_template_string(full, **kwargs)


# === Routes ===

@app.route("/")
def index():
    db = get_db()
    shelf_count = db.execute("SELECT COUNT(*) c FROM shelves").fetchone()["c"]
    item_count = db.execute("SELECT COUNT(*) c FROM shelf_items").fetchone()["c"]
    article_count = db.execute("SELECT COUNT(*) c FROM articles").fetchone()["c"]
    rakuten_count = db.execute("SELECT COUNT(*) c FROM rakuten_books").fetchone()["c"]
    shelf_stats = db.execute(
        """SELECT s.id, s.title, COUNT(si.id) as count
           FROM shelves s LEFT JOIN shelf_items si ON s.id = si.shelf_id
           GROUP BY s.id ORDER BY s.sort_order"""
    ).fetchall()
    db.close()
    return render("dashboard", active_tab="dashboard",
                  shelf_count=shelf_count, item_count=item_count,
                  article_count=article_count, rakuten_count=rakuten_count,
                  shelf_stats=shelf_stats)


# --- Shelf Items ---

@app.route("/items")
def shelf_items_list():
    db = get_db()
    shelves = db.execute("SELECT id, title FROM shelves ORDER BY sort_order").fetchall()
    filter_shelf = request.args.get("shelf", "")
    if filter_shelf:
        items = db.execute(
            "SELECT * FROM shelf_items WHERE shelf_id = ? ORDER BY sort_order", (filter_shelf,)
        ).fetchall()
    else:
        items = db.execute("SELECT * FROM shelf_items ORDER BY shelf_id, sort_order").fetchall()
    db.close()
    return render("items", active_tab="items", items=items, shelves=shelves, filter_shelf=filter_shelf)


@app.route("/items/add", methods=["GET", "POST"])
def shelf_item_add():
    db = get_db()
    shelves = db.execute("SELECT id, title FROM shelves ORDER BY sort_order").fetchall()
    if request.method == "POST":
        f = request.form
        db.execute(
            """INSERT INTO shelf_items (item_id, shelf_id, title, full_title, author,
               cover_url, amazon_url, rakuten_url, article_id, article_title,
               type, format, width, height, sort_order)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)""",
            (f["item_id"], f["shelf_id"], f["title"], f.get("full_title", ""),
             f.get("author", ""), f.get("cover_url", ""), f.get("amazon_url", ""),
             f.get("rakuten_url", ""), f.get("article_id", ""), f.get("article_title", ""),
             "product", f.get("format", "standard"),
             int(f.get("width") or 128), int(f.get("height") or 182),
             int(f.get("sort_order") or 0)),
        )
        db.commit()
        db.close()
        flash("アイテムを追加しました", "success")
        return redirect(url_for("shelf_items_list"))
    db.close()
    return render("item_form", active_tab="items", item=None, shelves=shelves)


@app.route("/items/<int:id>/edit", methods=["GET", "POST"])
def shelf_item_edit(id):
    db = get_db()
    shelves = db.execute("SELECT id, title FROM shelves ORDER BY sort_order").fetchall()
    if request.method == "POST":
        f = request.form
        db.execute(
            """UPDATE shelf_items SET item_id=?, shelf_id=?, title=?, full_title=?,
               author=?, cover_url=?, amazon_url=?, rakuten_url=?,
               article_id=?, article_title=?, format=?,
               width=?, height=?, sort_order=?
               WHERE id=?""",
            (f["item_id"], f["shelf_id"], f["title"], f.get("full_title", ""),
             f.get("author", ""), f.get("cover_url", ""), f.get("amazon_url", ""),
             f.get("rakuten_url", ""), f.get("article_id", ""), f.get("article_title", ""),
             f.get("format", "standard"),
             int(f.get("width") or 128), int(f.get("height") or 182),
             int(f.get("sort_order") or 0), id),
        )
        db.commit()
        db.close()
        flash("アイテムを更新しました", "success")
        return redirect(url_for("shelf_items_list"))
    item = db.execute("SELECT * FROM shelf_items WHERE id=?", (id,)).fetchone()
    db.close()
    return render("item_form", active_tab="items", item=item, shelves=shelves)


@app.route("/items/<int:id>/delete", methods=["POST"])
def shelf_item_delete(id):
    db = get_db()
    db.execute("DELETE FROM shelf_items WHERE id=?", (id,))
    db.commit()
    db.close()
    flash("アイテムを削除しました", "success")
    return redirect(url_for("shelf_items_list"))


# --- Articles ---

@app.route("/articles")
def articles_list():
    db = get_db()
    articles = db.execute("SELECT * FROM articles ORDER BY date DESC").fetchall()
    db.close()
    return render("articles", active_tab="articles", articles=articles)


@app.route("/articles/add", methods=["GET", "POST"])
def article_add():
    db = get_db()
    shelves = db.execute("SELECT id, title FROM shelves ORDER BY sort_order").fetchall()
    if request.method == "POST":
        f = request.form
        db.execute(
            "INSERT INTO articles (id, title, date, categories, shelf, product_count, url) VALUES (?,?,?,?,?,?,?)",
            (f["id"], f["title"], f.get("date", ""), "[]",
             f.get("shelf", ""), int(f.get("product_count") or 0), f.get("url", "")),
        )
        db.commit()
        db.close()
        flash("記事を追加しました", "success")
        return redirect(url_for("articles_list"))
    db.close()
    return render("article_form", active_tab="articles", article=None, shelves=shelves)


@app.route("/articles/<id>/edit", methods=["GET", "POST"])
def article_edit(id):
    db = get_db()
    shelves = db.execute("SELECT id, title FROM shelves ORDER BY sort_order").fetchall()
    if request.method == "POST":
        f = request.form
        db.execute(
            "UPDATE articles SET title=?, date=?, shelf=?, product_count=?, url=? WHERE id=?",
            (f["title"], f.get("date", ""), f.get("shelf", ""),
             int(f.get("product_count") or 0), f.get("url", ""), id),
        )
        db.commit()
        db.close()
        flash("記事を更新しました", "success")
        return redirect(url_for("articles_list"))
    article = db.execute("SELECT * FROM articles WHERE id=?", (id,)).fetchone()
    db.close()
    return render("article_form", active_tab="articles", article=article, shelves=shelves)


@app.route("/articles/<id>/delete", methods=["POST"])
def article_delete(id):
    db = get_db()
    db.execute("DELETE FROM articles WHERE id=?", (id,))
    db.commit()
    db.close()
    flash("記事を削除しました", "success")
    return redirect(url_for("articles_list"))


# --- Rakuten Books ---

@app.route("/rakuten")
def rakuten_list():
    db = get_db()
    filter_genre = request.args.get("genre", "")
    genres = [r["genre_id"] for r in db.execute("SELECT DISTINCT genre_id FROM rakuten_books ORDER BY genre_id").fetchall()]
    if filter_genre:
        books = db.execute("SELECT * FROM rakuten_books WHERE genre_id=? ORDER BY sort_order", (filter_genre,)).fetchall()
    else:
        books = db.execute("SELECT * FROM rakuten_books ORDER BY genre_id, sort_order").fetchall()
    db.close()
    return render("rakuten", active_tab="rakuten", books=books, genres=genres, filter_genre=filter_genre)


@app.route("/rakuten/add", methods=["GET", "POST"])
def rakuten_add():
    db = get_db()
    if request.method == "POST":
        f = request.form
        db.execute(
            """INSERT INTO rakuten_books (genre_id, isbn, title, author, publisher,
               item_price, item_url, large_image_url, item_caption, sales_date, sort_order)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)""",
            (f["genre_id"], f.get("isbn", ""), f["title"], f.get("author", ""),
             f.get("publisher", ""), int(f.get("item_price") or 0),
             f.get("item_url", ""), f.get("large_image_url", ""),
             f.get("item_caption", ""), f.get("sales_date", ""),
             int(f.get("sort_order") or 0)),
        )
        db.commit()
        db.close()
        flash("楽天書籍を追加しました", "success")
        return redirect(url_for("rakuten_list"))
    db.close()
    return render("rakuten_form", active_tab="rakuten", book=None)


@app.route("/rakuten/<int:id>/edit", methods=["GET", "POST"])
def rakuten_edit(id):
    db = get_db()
    if request.method == "POST":
        f = request.form
        db.execute(
            """UPDATE rakuten_books SET genre_id=?, isbn=?, title=?, author=?,
               publisher=?, item_price=?, item_url=?, large_image_url=?,
               item_caption=?, sales_date=?, sort_order=?
               WHERE id=?""",
            (f["genre_id"], f.get("isbn", ""), f["title"], f.get("author", ""),
             f.get("publisher", ""), int(f.get("item_price") or 0),
             f.get("item_url", ""), f.get("large_image_url", ""),
             f.get("item_caption", ""), f.get("sales_date", ""),
             int(f.get("sort_order") or 0), id),
        )
        db.commit()
        db.close()
        flash("楽天書籍を更新しました", "success")
        return redirect(url_for("rakuten_list"))
    book = db.execute("SELECT * FROM rakuten_books WHERE id=?", (id,)).fetchone()
    db.close()
    return render("rakuten_form", active_tab="rakuten", book=book)


@app.route("/rakuten/<int:id>/delete", methods=["POST"])
def rakuten_delete(id):
    db = get_db()
    db.execute("DELETE FROM rakuten_books WHERE id=?", (id,))
    db.commit()
    db.close()
    flash("楽天書籍を削除しました", "success")
    return redirect(url_for("rakuten_list"))


# --- Export ---

@app.route("/export")
def export_json():
    script = os.path.join(BASE_DIR, "scripts", "export_json.py")
    try:
        result = subprocess.run(
            [sys.executable, script],
            capture_output=True, text=True, cwd=BASE_DIR
        )
        if result.returncode == 0:
            flash(f"JSONエクスポート完了!\n{result.stdout}", "success")
        else:
            flash(f"エクスポートエラー: {result.stderr}", "error")
    except Exception as e:
        flash(f"エクスポート実行エラー: {e}", "error")
    return redirect(url_for("index"))


if __name__ == "__main__":
    if not os.path.exists(DB_PATH):
        print(f"DBが見つかりません: {DB_PATH}")
        print("先に python scripts/init_db.py を実行してください")
        sys.exit(1)
    print(f"管理画面起動: http://localhost:5000")
    print(f"DB: {DB_PATH}")
    app.run(debug=True, port=5000)
