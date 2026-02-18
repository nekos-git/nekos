#!/usr/bin/env python3
"""
Rewrite cover URLs in JSON data files to use local paths.
"""
import json
import os

DOCS = '/home/user/nekos/docs'

# Load mapping
with open(os.path.join(DOCS, 'cover-map.json')) as f:
    cover_map = json.load(f)

print(f"Loaded {len(cover_map)} URL mappings")

# Rewrite uz-shelf-data.json
uz_path = os.path.join(DOCS, 'uz-shelf-data.json')
with open(uz_path) as f:
    uz = json.load(f)

replaced_uz = 0
for shelf in uz['shelves']:
    for item in shelf['items']:
        url = item.get('coverUrl', '')
        if url in cover_map:
            item['coverUrl'] = cover_map[url]
            replaced_uz += 1

with open(uz_path, 'w', encoding='utf-8') as f:
    json.dump(uz, f, ensure_ascii=False, indent=2)
print(f"uz-shelf-data.json: {replaced_uz} URLs replaced")

# Rewrite rakuten JSONs
for fn in ['001005.json', '001006.json', '001010.json']:
    fpath = os.path.join(DOCS, fn)
    with open(fpath) as f:
        data = json.load(f)

    replaced = 0
    for entry in data['Items']:
        for key in ['largeImageUrl', 'mediumImageUrl', 'smallImageUrl']:
            url = entry['Item'].get(key, '')
            if url in cover_map:
                entry['Item'][key] = cover_map[url]
                replaced += 1

    with open(fpath, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    print(f"{fn}: {replaced} URLs replaced")

print("\nDone!")
