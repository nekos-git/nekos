#!/usr/bin/env python3
"""Parse uz-media.com export and extract article metadata + affiliate product data."""
import re
import json
import html
from collections import defaultdict

def parse_export(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Split by article delimiter
    raw_articles = content.split('--------\n')
    articles = []

    for raw in raw_articles:
        raw = raw.strip()
        if not raw:
            continue

        article = {}
        # Extract metadata
        title_m = re.search(r'^TITLE:\s*(.+)$', raw, re.MULTILINE)
        basename_m = re.search(r'^BASENAME:\s*(.+)$', raw, re.MULTILINE)
        date_m = re.search(r'^DATE:\s*(.+)$', raw, re.MULTILINE)
        status_m = re.search(r'^STATUS:\s*(.+)$', raw, re.MULTILINE)
        categories = re.findall(r'^CATEGORY:\s*(.+)$', raw, re.MULTILINE)

        if not title_m:
            continue

        article['title'] = title_m.group(1).strip()
        article['basename'] = basename_m.group(1).strip() if basename_m else ''
        article['date'] = date_m.group(1).strip() if date_m else ''
        article['status'] = status_m.group(1).strip() if status_m else ''
        article['categories'] = categories

        # Extract body
        body_m = re.search(r'^BODY:\n(.+?)(?:\n-----|\Z)', raw, re.DOTALL | re.MULTILINE)
        body = body_m.group(1) if body_m else ''
        article['body_html'] = body

        # Extract msmaflink product data
        products = []
        for m in re.finditer(r'msmaflink\((\{.*?\})\)', body, re.DOTALL):
            try:
                # Clean up JS object to valid JSON
                js_obj = m.group(1)
                # Handle JS-style keys without quotes
                js_obj = re.sub(r'(?<=[{,])\s*"?(\w+)"?\s*:', r'"\1":', js_obj)
                # Fix escaped chars
                js_obj = js_obj.replace('\\"', '"')
                product = json.loads(js_obj)
                products.append({
                    'name': product.get('n', ''),
                    'brand': product.get('b', ''),
                    'images': product.get('p', []),
                    'image_base': product.get('c_p', ''),
                    'domain': product.get('d', ''),
                    'links': product.get('b_l', []),
                    'eid': product.get('eid', ''),
                })
            except (json.JSONDecodeError, Exception):
                # Try simpler extraction
                name_m = re.search(r'"n"\s*:\s*"([^"]+)"', m.group(1))
                brand_m = re.search(r'"b"\s*:\s*"([^"]+)"', m.group(1))
                img_m = re.findall(r'"(/images/I/[^"]+)"', m.group(1))
                domain_m = re.search(r'"d"\s*:\s*"([^"]+)"', m.group(1))
                eid_m = re.search(r'"eid"\s*:\s*"([^"]+)"', m.group(1))

                amazon_url = ''
                rakuten_url = ''
                for link_m in re.finditer(r'"u_url"\s*:\s*"([^"]+)"', m.group(1)):
                    url = link_m.group(1).replace('\\/', '/')
                    if 'amazon' in url:
                        amazon_url = url
                    elif 'rakuten' in url:
                        rakuten_url = url

                products.append({
                    'name': name_m.group(1) if name_m else '',
                    'brand': brand_m.group(1) if brand_m else '',
                    'images': img_m,
                    'image_base': '',
                    'domain': domain_m.group(1) if domain_m else '',
                    'amazon_url': amazon_url,
                    'rakuten_url': rakuten_url,
                    'eid': eid_m.group(1) if eid_m else '',
                })

        article['products'] = products

        # Extract plain text for title analysis
        plain = re.sub(r'<[^>]+>', '', body)
        plain = html.unescape(plain)
        article['plain_text_preview'] = plain[:500]

        articles.append(article)

    return articles

def classify_article(article):
    """Classify article into shelf categories based on categories and content."""
    cats = [c.lower() for c in article['categories']]
    title = article['title'].lower()

    # Primary classification
    if any('マンガ' in c or 'アニメ' in c for c in article['categories']):
        return 'manga'
    if any('映画' in c for c in article['categories']):
        return 'film'
    if any('音楽' in c for c in article['categories']):
        return 'music'
    if any('小説' in c for c in article['categories']):
        return 'books'
    if any('IT' in c or 'テクノロジー' in c for c in article['categories']):
        return 'tech'

    # Fallback by title keywords
    film_kw = ['映画', 'ノーラン', 'リンチ', 'ウォン・カーウァイ', '観たい']
    manga_kw = ['マンガ', '漫画', 'チェンソーマン', 'ベルセルク', 'ジャンプ', 'ナウシカ']
    music_kw = ['サカナクション', 'フィッシュマンズ', 'Oasis', 'Radiohead', '宇多田', '坂本龍一']
    book_kw = ['村上春樹', 'コエーリョ', '伊藤計劃', '川上未映子', 'トフラー']

    for kw in film_kw:
        if kw in article['title']:
            return 'film'
    for kw in manga_kw:
        if kw in article['title']:
            return 'manga'
    for kw in music_kw:
        if kw in article['title']:
            return 'music'
    for kw in book_kw:
        if kw in article['title']:
            return 'books'

    return 'culture'  # Default

def build_shelf_data(articles):
    """Build shelf JSON data from articles."""
    shelves = defaultdict(list)

    for art in articles:
        cat = classify_article(art)

        # Create a cover image URL from the first product image or article og image
        cover_url = ''
        if art['products']:
            p = art['products'][0]
            if p.get('images') and p.get('domain'):
                img_path = p['images'][0] if p['images'] else ''
                base = p.get('image_base', '/images/I')
                cover_url = f"{p['domain']}{base}{img_path}" if img_path else ''

        # Extract og:image from body if available
        if not cover_url:
            og_m = re.search(r'<img[^>]+src="([^"]+)"', art['body_html'])
            if og_m:
                cover_url = og_m.group(1)

        item = {
            'id': art['basename'],
            'title': art['title'],
            'date': art['date'],
            'categories': art['categories'],
            'shelf': cat,
            'coverUrl': cover_url,
            'articleUrl': f"https://uz-media.com/entry/{art['basename']}",
            'products': [],
        }

        for p in art['products']:
            product = {
                'name': p['name'],
                'brand': p.get('brand', ''),
                'coverUrl': '',
                'amazonUrl': '',
                'rakutenUrl': '',
            }
            # Build image URL
            if p.get('images') and p.get('domain'):
                base = p.get('image_base', '/images/I')
                product['coverUrl'] = f"{p['domain']}{base}{p['images'][0]}"

            # Extract URLs
            if p.get('amazon_url'):
                product['amazonUrl'] = p['amazon_url']
            if p.get('rakuten_url'):
                product['rakutenUrl'] = p['rakuten_url']

            if isinstance(p.get('links'), list):
                for link in p['links']:
                    if isinstance(link, dict):
                        url = link.get('u_url', '').replace('\\/', '/')
                        if 'amazon' in url:
                            product['amazonUrl'] = url
                        elif 'rakuten' in url:
                            product['rakutenUrl'] = url

            if product['name']:
                item['products'].append(product)

        shelves[cat].append(item)

    return dict(shelves)

def main():
    filepath = '/home/user/nekos/uz/uz-media.com.export.txt'
    articles = parse_export(filepath)
    print(f"Parsed {len(articles)} articles")

    shelf_data = build_shelf_data(articles)
    for cat, items in shelf_data.items():
        product_count = sum(len(i['products']) for i in items)
        print(f"  {cat}: {len(items)} articles, {product_count} products")

    # Build the final JSON
    output = {
        'shelves': [],
        'articles': []
    }

    shelf_config = {
        'books': {'title': '小説・文学', 'icon': 'book'},
        'manga': {'title': 'マンガ・アニメ', 'icon': 'manga'},
        'film': {'title': '映画・DVD', 'icon': 'film'},
        'music': {'title': '音楽', 'icon': 'music'},
        'tech': {'title': 'IT・テクノロジー', 'icon': 'tech'},
        'culture': {'title': 'カルチャー', 'icon': 'culture'},
    }

    for cat_id, config in shelf_config.items():
        items = shelf_data.get(cat_id, [])
        if items:
            # Collect all products as shelf items
            shelf_items = []
            for art in items:
                for p in art['products']:
                    shelf_items.append({
                        'id': f"{art['id']}_{p.get('eid', '')}",
                        'title': p['name'][:40] if p['name'] else art['title'][:40],
                        'fullTitle': p['name'] or art['title'],
                        'author': p.get('brand', ''),
                        'coverUrl': p['coverUrl'],
                        'amazonUrl': p.get('amazonUrl', ''),
                        'rakutenUrl': p.get('rakutenUrl', ''),
                        'articleId': art['id'],
                        'articleTitle': art['title'],
                        'type': 'product',
                    })

            output['shelves'].append({
                'id': cat_id,
                'title': config['title'],
                'icon': config['icon'],
                'items': shelf_items,
            })

    # Articles list
    for art in articles:
        output['articles'].append({
            'id': art['basename'],
            'title': art['title'],
            'date': art['date'],
            'categories': art['categories'],
            'shelf': classify_article(art),
            'productCount': len(art['products']),
            'url': f"https://uz-media.com/entry/{art['basename']}",
        })

    with open('/home/user/nekos/book/booksUI/uz-shelf-data.json', 'w', encoding='utf-8') as f:
        json.dump(output, f, ensure_ascii=False, indent=2)

    print(f"\nWrote uz-shelf-data.json")
    print(f"Total shelves: {len(output['shelves'])}")
    print(f"Total articles: {len(output['articles'])}")
    for s in output['shelves']:
        print(f"  {s['title']}: {len(s['items'])} items")

if __name__ == '__main__':
    main()
