#!/bin/bash
# Fetch cover images for 11 remaining articles using known Amazon image URLs
# These are publicly accessible product images for books/DVDs related to each article

COVERS_DIR="/home/user/nekos/docs/covers-extra"
mkdir -p "$COVERS_DIR"

declare -A URLS
# Ghost in the Shell manga (Shirow Masamune)
URLS[ghost-in-the-shell]="https://m.media-amazon.com/images/I/61nJHM9h4DL._SL800_.jpg"
# Evangelion - Sadamoto manga
URLS[eva-jinruihokankeikaku]="https://m.media-amazon.com/images/I/51U26kpMryL._SL800_.jpg"
# Paulo Coelho - The Alchemist (Japanese)
URLS[murakami-and-pauro]="https://m.media-amazon.com/images/I/41E2NXG9A9L._SL800_.jpg"
# Kawakami Mieko - Heaven
URLS[mieko-kawakami]="https://m.media-amazon.com/images/I/31LOq9p0dKL._SL800_.jpg"
# Paulo Coelho - Brida
URLS[pauro-witch]="https://m.media-amazon.com/images/I/41FU0vjiNpL._SL800_.jpg"
# Hosoda Mamoru - Mirai
URLS[hosodamamoru]="https://m.media-amazon.com/images/I/51HlpM2Y9bL._SL800_.jpg"
# AI Robot - iRobot/Asimov
URLS[ai-robot-movie]="https://m.media-amazon.com/images/I/51hFpXgbTwL._SL800_.jpg"
# Zootopia art book
URLS[zootopia]="https://m.media-amazon.com/images/I/51R0bJOfBkL._SL800_.jpg"
# Kiriya Kazuaki - GOEMON
URLS[kiriya-kazuaki]="https://m.media-amazon.com/images/I/51KF9Qo8qwL._SL800_.jpg"
# Hidden masterpiece movies
URLS[underrated-masterpiece]="https://m.media-amazon.com/images/I/51VHvrK5BsL._SL800_.jpg"
# Mac vs Windows
URLS[mac-or-win-2025]="https://m.media-amazon.com/images/I/41Fiktmp33L._SL800_.jpg"

for slug in "${!URLS[@]}"; do
    outfile="$COVERS_DIR/${slug}-cover.jpg"
    url="${URLS[$slug]}"

    if [ -f "$outfile" ] && [ "$(wc -c < "$outfile")" -gt 100 ]; then
        echo "SKIP (exists): $slug"
        continue
    fi

    curl -sL -o "$outfile" "$url"

    if [ -f "$outfile" ] && [ "$(wc -c < "$outfile")" -gt 100 ]; then
        size=$(wc -c < "$outfile")
        echo "OK: $slug ($size bytes)"
    else
        echo "FAIL: $slug (url: $url)"
        rm -f "$outfile"
    fi
done

echo "---"
ls -la "$COVERS_DIR/"
