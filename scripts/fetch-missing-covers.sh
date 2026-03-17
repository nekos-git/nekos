#!/bin/bash
# Fetch cover images for articles without thumbnails using Google Books API + OpenBD
# Then upload via wp media import

COVERS_DIR="/home/user/nekos/docs/covers-extra"
mkdir -p "$COVERS_DIR"

# Article slug -> search query + fallback ISBN
declare -A QUERIES
QUERIES[ai-robot-movie]="ロボット+SF+映画"
QUERIES[hosodamamoru]="細田守+アニメーション"
QUERIES[ghost-in-the-shell]="攻殻機動隊+士郎正宗"
QUERIES[zootopia]="ズートピア+ディズニー"
QUERIES[eva-jinruihokankeikaku]="新世紀エヴァンゲリオン"
QUERIES[murakami-and-pauro]="アルケミスト+パウロコエーリョ"
QUERIES[kiriya-kazuaki]="紀里谷和明"
QUERIES[mieko-kawakami]="川上未映子+ヘヴン"
QUERIES[underrated-masterpiece]="映画+名作+隠れた"
QUERIES[pauro-witch]="パウロコエーリョ+ブリーダ"
QUERIES[mac-or-win-2025]="Mac+Windows+比較"

# ISBNs for direct OpenBD lookup (more reliable for getting covers)
declare -A ISBNS
ISBNS[ghost-in-the-shell]="9784063210781"
ISBNS[eva-jinruihokankeikaku]="9784041000748"
ISBNS[murakami-and-pauro]="9784042750017"
ISBNS[mieko-kawakami]="9784062772631"
ISBNS[pauro-witch]="9784042750093"
ISBNS[hosodamamoru]="9784041074510"

for slug in "${!QUERIES[@]}"; do
    outfile="$COVERS_DIR/${slug}-cover.jpg"
    if [ -f "$outfile" ]; then
        echo "SKIP (exists): $slug"
        continue
    fi

    image_url=""

    # Try OpenBD first if we have an ISBN
    if [ -n "${ISBNS[$slug]}" ]; then
        isbn="${ISBNS[$slug]}"
        cover=$(curl -s "https://api.openbd.jp/v1/get?isbn=$isbn" | python3 -c "
import json, sys
try:
    d = json.load(sys.stdin)
    if d and d[0] and d[0].get('summary',{}).get('cover'):
        print(d[0]['summary']['cover'])
except:
    pass
" 2>/dev/null)
        if [ -n "$cover" ]; then
            image_url="$cover"
            echo "OpenBD hit ($isbn): $image_url"
        fi
    fi

    # Try Google Books API
    if [ -z "$image_url" ]; then
        query="${QUERIES[$slug]}"
        gb_url="https://www.googleapis.com/books/v1/volumes?q=$(python3 -c "import urllib.parse; print(urllib.parse.quote('$query'))")&langRestrict=ja&maxResults=1"
        image_url=$(curl -s "$gb_url" | python3 -c "
import json, sys
try:
    d = json.load(sys.stdin)
    links = d.get('items',[{}])[0].get('volumeInfo',{}).get('imageLinks',{})
    url = links.get('thumbnail','') or links.get('smallThumbnail','')
    if url:
        print(url.replace('http://','https://'))
except:
    pass
" 2>/dev/null)
        if [ -n "$image_url" ]; then
            echo "Google Books hit: $image_url"
        fi
    fi

    if [ -z "$image_url" ]; then
        echo "FAIL (no image): $slug"
        continue
    fi

    # Download
    curl -sL -o "$outfile" "$image_url"
    if [ -f "$outfile" ] && [ -s "$outfile" ]; then
        echo "OK: $slug -> $outfile"
    else
        echo "FAIL (download): $slug"
        rm -f "$outfile"
    fi

    sleep 1
done

echo "--- Download complete ---"
ls -la "$COVERS_DIR/"
