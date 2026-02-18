#!/usr/bin/env python3
"""
Download all cover images from external URLs and save locally.
Creates a mapping file (cover-map.json) for URL → local path.
"""
import json
import os
import hashlib
import urllib.request
import urllib.error
import ssl
import time
import sys

DOCS = '/home/user/nekos/docs'
COVERS_DIR = os.path.join(DOCS, 'covers')
os.makedirs(COVERS_DIR, exist_ok=True)

# Domains that serve actual images
IMAGE_DOMAINS = [
    'thumbnail.image.rakuten.co.jp',
    'm.media-amazon.com',
]

def is_image_url(url):
    if not url:
        return False
    for d in IMAGE_DOMAINS:
        if d in url:
            return True
    return False

def url_to_filename(url):
    """Create a stable, unique filename from URL."""
    h = hashlib.md5(url.encode()).hexdigest()[:12]
    # Extract extension
    path = url.split('?')[0]
    ext = os.path.splitext(path)[1]
    if ext not in ('.jpg', '.jpeg', '.png', '.gif', '.webp'):
        ext = '.jpg'
    return f"{h}{ext}"

def download_image(url, filepath, retries=3):
    """Download image with retries."""
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE

    for attempt in range(retries):
        try:
            req = urllib.request.Request(url, headers={
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            })
            with urllib.request.urlopen(req, timeout=10, context=ctx) as resp:
                data = resp.read()
                if len(data) < 100:  # Too small, probably not a real image
                    return False
                with open(filepath, 'wb') as f:
                    f.write(data)
                return True
        except Exception as e:
            if attempt < retries - 1:
                time.sleep(1)
            else:
                print(f"  FAIL: {url} -> {e}", file=sys.stderr)
                return False
    return False

# Collect all image URLs
all_urls = set()

# From uz-shelf-data.json
with open(os.path.join(DOCS, 'uz-shelf-data.json')) as f:
    uz = json.load(f)
for shelf in uz['shelves']:
    for item in shelf['items']:
        if is_image_url(item.get('coverUrl', '')):
            all_urls.add(item['coverUrl'])

# From rakuten JSONs
for fn in ['001005.json', '001006.json', '001010.json']:
    with open(os.path.join(DOCS, fn)) as f:
        data = json.load(f)
    for entry in data['Items']:
        url = entry['Item'].get('largeImageUrl', '')
        if is_image_url(url):
            all_urls.add(url)

print(f"Total unique image URLs to download: {len(all_urls)}")

# Download all
cover_map = {}
success = 0
fail = 0

for i, url in enumerate(sorted(all_urls)):
    fname = url_to_filename(url)
    fpath = os.path.join(COVERS_DIR, fname)
    local_rel = f"covers/{fname}"

    if os.path.exists(fpath) and os.path.getsize(fpath) > 100:
        cover_map[url] = local_rel
        success += 1
        continue

    ok = download_image(url, fpath)
    if ok:
        cover_map[url] = local_rel
        success += 1
    else:
        fail += 1

    if (i + 1) % 20 == 0:
        print(f"  Progress: {i+1}/{len(all_urls)} (ok={success}, fail={fail})")

print(f"\nDone: {success} downloaded, {fail} failed")

# Save mapping
with open(os.path.join(DOCS, 'cover-map.json'), 'w') as f:
    json.dump(cover_map, f, indent=2)

print(f"Saved cover-map.json with {len(cover_map)} entries")
