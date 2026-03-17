#!/usr/bin/env python3
"""
Upload cover images to production WordPress site via REST API.
Sets each image as the featured image for the corresponding article.

Usage: python3 scripts/upload-covers-production.py
"""

import base64
import json
import os
import sys
import time
import urllib.request
import urllib.parse
import ssl

SITE_URL = "https://end2endworld.org/wp"
API_BASE = f"{SITE_URL}/wp-json/wp/v2"
USERNAME = "jun1"
APP_PASSWORD = "9bla kItk kP8V NXTE FZcB te7t"

# Directories with cover images
COVERS_DIR = "/home/user/nekos/docs/covers"
COVERS_EXTRA_DIR = "/home/user/nekos/docs/covers-extra"
SHELF_DATA = "/home/user/nekos/docs/uz-shelf-data.json"

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE


def make_auth_header():
    credentials = base64.b64encode(f"{USERNAME}:{APP_PASSWORD}".encode()).decode()
    return {"Authorization": f"Basic {credentials}"}


def api_request(method, url, data=None, files=None, headers=None):
    """Make authenticated API request."""
    hdrs = make_auth_header()
    hdrs["User-Agent"] = "Mozilla/5.0"
    if headers:
        hdrs.update(headers)

    if data and not files:
        body = json.dumps(data).encode()
        hdrs["Content-Type"] = "application/json"
    elif files:
        body = files
    else:
        body = None

    req = urllib.request.Request(url, data=body, headers=hdrs, method=method)
    try:
        with urllib.request.urlopen(req, timeout=60, context=ctx) as resp:
            return json.loads(resp.read().decode())
    except urllib.error.HTTPError as e:
        error_body = e.read().decode() if e.fp else ""
        print(f"  HTTP {e.code}: {error_body[:200]}")
        return None
    except Exception as e:
        print(f"  Error: {e}")
        return None


def upload_media(filepath, filename, post_id=None):
    """Upload a file to WordPress media library."""
    with open(filepath, "rb") as f:
        file_data = f.read()

    hdrs = make_auth_header()
    hdrs["User-Agent"] = "Mozilla/5.0"
    hdrs["Content-Type"] = "image/jpeg"
    hdrs["Content-Disposition"] = f'attachment; filename="{filename}"'

    url = f"{API_BASE}/media"
    if post_id:
        url += f"?post={post_id}"

    req = urllib.request.Request(url, data=file_data, headers=hdrs, method="POST")
    try:
        with urllib.request.urlopen(req, timeout=60, context=ctx) as resp:
            return json.loads(resp.read().decode())
    except urllib.error.HTTPError as e:
        error_body = e.read().decode() if e.fp else ""
        print(f"  Upload HTTP {e.code}: {error_body[:200]}")
        return None
    except Exception as e:
        print(f"  Upload error: {e}")
        return None


def set_featured_image(post_id, media_id):
    """Set featured image for a post."""
    url = f"{API_BASE}/posts/{post_id}"
    return api_request("POST", url, data={"featured_media": media_id})


def get_articles():
    """Get all articles from bookshelf API."""
    url = f"{SITE_URL}/wp-json/uz-bookshelf/v1/articles"
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req, timeout=30, context=ctx) as resp:
        return json.loads(resp.read().decode())


def get_post_by_slug(slug):
    """Get WordPress post by slug."""
    url = f"{API_BASE}/posts?slug={urllib.parse.quote(slug)}&_fields=id,slug,featured_media"
    hdrs = make_auth_header()
    hdrs["User-Agent"] = "Mozilla/5.0"
    req = urllib.request.Request(url, headers=hdrs)
    try:
        with urllib.request.urlopen(req, timeout=30, context=ctx) as resp:
            posts = json.loads(resp.read().decode())
            return posts[0] if posts else None
    except:
        return None


def main():
    # Load shelf data for article -> cover mapping
    with open(SHELF_DATA) as f:
        shelf_data = json.load(f)

    # Build article_id -> first cover path
    article_covers = {}
    for shelf in shelf_data["shelves"]:
        for item in shelf["items"]:
            aid = item.get("articleId", "")
            cover = item.get("coverUrl", "")
            if aid and cover and aid not in article_covers:
                article_covers[aid] = cover

    # Get articles
    articles = get_articles()
    print(f"Found {len(articles)} articles")

    uploaded = 0
    skipped = 0
    failed = 0

    for art in articles:
        slug = art["id"]
        thumb = art.get("thumbnailUrl", "")

        # Find cover image
        cover_file = None

        # Check covers-extra first (for articles without shelf items)
        extra_path = os.path.join(COVERS_EXTRA_DIR, f"{slug}-cover.jpg")
        if os.path.exists(extra_path) and os.path.getsize(extra_path) > 100:
            cover_file = extra_path

        # Check shelf data covers
        if not cover_file and slug in article_covers:
            shelf_cover = os.path.join("/home/user/nekos/docs", article_covers[slug])
            if os.path.exists(shelf_cover):
                cover_file = shelf_cover

        if not cover_file:
            print(f"SKIP (no local cover): {slug}")
            skipped += 1
            continue

        # Get WP post ID
        post = get_post_by_slug(slug)
        if not post:
            print(f"SKIP (post not found): {slug}")
            skipped += 1
            continue

        post_id = post["id"]

        # Skip if already has featured image
        if post.get("featured_media", 0) > 0:
            print(f"SKIP (has thumbnail): {slug}")
            skipped += 1
            continue

        # Upload image
        print(f"Uploading: {slug} ({os.path.basename(cover_file)})...", end=" ")
        media = upload_media(cover_file, f"{slug}-cover.jpg", post_id)
        if not media:
            print("FAILED")
            failed += 1
            time.sleep(1)
            continue

        media_id = media["id"]

        # Set as featured image
        result = set_featured_image(post_id, media_id)
        if result:
            print(f"OK (media #{media_id})")
            uploaded += 1
        else:
            print(f"FAILED (set thumbnail)")
            failed += 1

        time.sleep(0.5)  # Be polite

    print(f"\n=== Done ===")
    print(f"Uploaded: {uploaded}, Skipped: {skipped}, Failed: {failed}")


if __name__ == "__main__":
    main()
