#!/usr/bin/env python3
"""
Fetch cover images for articles without thumbnails.
Uses Google Books API and OpenBD (Japanese book API).
"""

import json
import os
import sys
import time
import urllib.request
import urllib.parse
import ssl

COVERS_DIR = "/home/user/nekos/docs/covers-extra"
os.makedirs(COVERS_DIR, exist_ok=True)

# Disable SSL verification (some environments have issues)
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

# Article slug -> (search query, ISBN for OpenBD)
ARTICLES = {
    "ghost-in-the-shell": ("攻殻機動隊 士郎正宗", "9784063210781"),
    "eva-jinruihokankeikaku": ("新世紀エヴァンゲリオン", "9784049120011"),
    "murakami-and-pauro": ("アルケミスト パウロ・コエーリョ", "9784042750017"),
    "mieko-kawakami": ("川上未映子 ヘヴン", "9784062772631"),
    "pauro-witch": ("ブリーダ パウロ・コエーリョ", "9784042750048"),
    "hosodamamoru": ("細田守 未来のミライ", "9784041074510"),
    "ai-robot-movie": ("ロボット SF 映画論", ""),
    "zootopia": ("ズートピア ディズニー", ""),
    "kiriya-kazuaki": ("紀里谷和明", ""),
    "underrated-masterpiece": ("映画 名作 隠れた", ""),
    "mac-or-win-2025": ("Mac Windows 選び方", ""),
}


def fetch_url(url):
    """Fetch URL content."""
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=15, context=ctx) as resp:
            return resp.read()
    except Exception as e:
        print(f"  Fetch error: {e}")
        return None


def try_openbd(isbn):
    """Try OpenBD API for cover image."""
    if not isbn:
        return None
    url = f"https://api.openbd.jp/v1/get?isbn={isbn}"
    data = fetch_url(url)
    if data:
        try:
            result = json.loads(data)
            if result and result[0]:
                cover = result[0].get("summary", {}).get("cover", "")
                if cover:
                    return cover
        except:
            pass
    return None


def try_google_books(query):
    """Try Google Books API for cover image."""
    encoded = urllib.parse.quote(query)
    url = f"https://www.googleapis.com/books/v1/volumes?q={encoded}&langRestrict=ja&maxResults=1"
    data = fetch_url(url)
    if data:
        try:
            result = json.loads(data)
            items = result.get("items", [])
            if items:
                links = items[0].get("volumeInfo", {}).get("imageLinks", {})
                cover = links.get("thumbnail") or links.get("smallThumbnail")
                if cover:
                    return cover.replace("http://", "https://")
        except:
            pass
    return None


def download_image(url, filepath):
    """Download image to filepath."""
    data = fetch_url(url)
    if data and len(data) > 100:  # Sanity check: valid image > 100 bytes
        with open(filepath, "wb") as f:
            f.write(data)
        return True
    return False


def main():
    results = {"ok": [], "fail": []}

    for slug, (query, isbn) in ARTICLES.items():
        outfile = os.path.join(COVERS_DIR, f"{slug}-cover.jpg")
        if os.path.exists(outfile) and os.path.getsize(outfile) > 100:
            print(f"SKIP (exists): {slug}")
            results["ok"].append(slug)
            continue

        print(f"\nProcessing: {slug}")
        image_url = None

        # Try OpenBD
        if isbn:
            image_url = try_openbd(isbn)
            if image_url:
                print(f"  OpenBD hit: {image_url}")

        # Try Google Books
        if not image_url:
            image_url = try_google_books(query)
            if image_url:
                print(f"  Google Books hit: {image_url}")

        if not image_url:
            print(f"  FAIL: No image found for {slug}")
            results["fail"].append(slug)
            continue

        if download_image(image_url, outfile):
            size = os.path.getsize(outfile)
            print(f"  OK: {slug} ({size} bytes)")
            results["ok"].append(slug)
        else:
            print(f"  FAIL: Download failed for {slug}")
            results["fail"].append(slug)

        time.sleep(1)

    print(f"\n=== Done ===")
    print(f"OK: {len(results['ok'])}, FAIL: {len(results['fail'])}")
    if results["fail"]:
        print(f"Failed: {', '.join(results['fail'])}")


if __name__ == "__main__":
    main()
