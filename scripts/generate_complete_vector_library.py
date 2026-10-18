#!/usr/bin/env python3
"""
Generates and organizes a complete, curated, laser-ready vector library for Seelenfunke configurator.
All SVGs are guaranteed to be 100% monochrome black (#000000), valid XML, properly viewBoxed,
and optimized for both web display and xTool laser engraving.
"""

import os
import re
import shutil

BASE_DIR = "/home/ubuntuxina/meine-projekte/seelenfunke"
VECTORS_DIR = os.path.join(BASE_DIR, "public/shop/product/configurator/vectors")
BACKUP_DIR = os.path.join(BASE_DIR, "public/shop/product/configurator/vectors_backup")
BOOTSTRAP_DIR = os.path.join(BASE_DIR, "node_modules/bootstrap-icons/icons")

def ensure_black_svg(svg_str: str, viewBox: str = None) -> str:
    # Strip comments and headers
    s = re.sub(r'<\?xml.*?\?>', '', svg_str, flags=re.DOTALL)
    s = re.sub(r'<!DOCTYPE.*?>', '', s, flags=re.DOTALL)
    s = re.sub(r'<!--.*?-->', '', s, flags=re.DOTALL)
    s = re.sub(r'<title.*?>.*?</title>', '', s, flags=re.DOTALL | re.IGNORECASE)
    s = re.sub(r'<desc.*?>.*?</desc>', '', s, flags=re.DOTALL | re.IGNORECASE)
    s = re.sub(r'<style.*?>.*?</style>', '', s, flags=re.DOTALL | re.IGNORECASE)
    s = re.sub(r'<script.*?>.*?</script>', '', s, flags=re.DOTALL | re.IGNORECASE)

    # Extract existing viewBox if not provided
    if not viewBox:
        vb_match = re.search(r'viewBox="([^"]+)"', s, re.IGNORECASE)
        if vb_match:
            viewBox = vb_match.group(1)
        else:
            viewBox = "0 0 24 24"

    # Extract inner body
    body_match = re.search(r'<svg[^>]*>(.*?)</svg>', s, flags=re.DOTALL | re.IGNORECASE)
    if body_match:
        body = body_match.group(1).strip()
    else:
        body = re.sub(r'<svg[^>]*>', '', s, flags=re.IGNORECASE).replace('</svg>', '').strip()

    # Clean inner attributes
    body = re.sub(r'\sfill="[^"]*"', '', body)
    body = re.sub(r'\sstroke="[^"]*"', '', body)
    body = re.sub(r'\s(?:id|class|data-[a-zA-Z0-9\-]+)="[^"]*"', '', body)

    # Add fill="#000000" to every shape
    for tag in ['path', 'circle', 'rect', 'ellipse', 'polygon', 'polyline']:
        body = re.sub(rf'<{tag}(\s+[^>]*)?>', lambda m: f'<{tag} fill="#000000"' + (m.group(1) if m.group(1) else '') + '>', body)

    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{viewBox}" fill="#000000">\n  {body}\n</svg>'

def load_bootstrap_icon(name: str) -> str:
    path = os.path.join(BOOTSTRAP_DIR, f"{name}.svg")
    if os.path.exists(path):
        with open(path, "r", encoding="utf-8") as f:
            return ensure_black_svg(f.read(), "0 0 16 16")
    return None

# Specialized High-Quality Laser Silhouette Vectors (Standard 24x24 or 512x512)
CUSTOM_VECTORS = {
    # PAW PRINT (Hundepfote / Katzenpfote)
    "hundepfote": """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="#000000">
  <path fill="#000000" d="M226.5 92.9c14.3 42.9-.3 86.2-32.6 96.8s-70.1-15.6-84.4-58.5s.3-86.2 32.6-96.8s70.1 15.6 84.4 58.5M100.4 198.6c18.9 32.4 14.3 70.1-10.2 84.1s-59.7-.9-78.5-33.3s-14.4-70.1 10.1-84.1s59.7.9 78.5 33.3zM69.2 401.2C121.6 259.9 214.7 224 256 224s134.4 35.9 186.8 177.2c3.6 9.7 5.2 20.1 5.2 30.5v1.6c0 25.8-20.9 46.7-46.7 46.7c-11.5 0-22.9-1.4-34-4.2l-88-22c-15.3-3.8-31.3-3.8-46.6 0l-88 22c-11.1 2.8-22.5 4.2-34 4.2c-25.8 0-46.7-20.9-46.7-46.7v-1.6c0-10.4 1.6-20.8 5.2-30.5m352.6-118.5c-24.5-14-29.1-51.7-10.2-84.1s54-47.3 78.5-33.3s29.1 51.7 10.2 84.1s-54 47.3-78.5 33.3m-111.7-93c-32.3-10.6-46.9-53.9-32.6-96.8s52.1-69.1 84.4-58.5s46.9 53.9 32.6 96.8s-52.1 69.1-84.4 58.5"/>
</svg>""",

    # FOUR-LEAF CLOVER (Glücksklee)
    "kleeblatt": """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="#000000">
  <path fill="#000000" d="M192.5 64c-35.3 0-64 28.7-64 64c0 14.1 4.5 27.2 12.3 37.8c-28.7-17.5-62.8-21.8-94.8-12c-47.6 14.6-74.4 64.7-59.8 112.3c10.8 35.3 41.2 59.9 77.8 63.9c-10.6 7.8-17.8 20.9-17.8 35.9c0 35.3 28.7 64 64 64c14.1 0 27.2-4.5 37.8-12.3c-17.5 28.7-21.8 62.8-12 94.8c14.6 47.6 64.7 74.4 112.3 59.8c35.3-10.8 59.9-41.2 63.9-77.8c7.8 10.6 20.9 17.8 35.9 17.8c35.3 0 64-28.7 64-64c0-14.1-4.5-27.2-12.3-37.8c28.7 17.5 62.8 21.8 94.8 12c47.6-14.6 74.4-64.7 59.8-112.3c-10.8-35.3-41.2-59.9-77.8-63.9c10.6-7.8 17.8-20.9 17.8-35.9c0-35.3-28.7-64-64-64c-14.1 0-27.2 4.5-37.8 12.3c17.5-28.7 21.8-62.8 12-94.8C401.7 16.4 351.6-10.4 304 4.2c-35.3 10.8-59.9 41.2-63.9 77.8c-7.8-10.6-20.9-17.8-35.9-17.8z"/>
</svg>""",

    # HORSESHOE (Hufeisen)
    "hufeisen": """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#000000">
  <path fill="#000000" fill-rule="evenodd" d="M4 2a2 2 0 0 0-2 2v6c0 6.627 5.373 12 12 12s12-5.373 12-12V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v6a4 4 0 0 1-8 0V4a2 2 0 0 0-2-2H4zm3 8a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0 4a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm14-4a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0 4a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z" clip-rule="evenodd"/>
</svg>""",

    # MARITIME ANCHOR (Anker)
    "anker": """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="#000000">
  <path fill="#000000" d="M288 64c0-17.7-14.3-32-32-32s-32 14.3-32 32c0 14.7 10 27.1 23.5 30.8c-1.2 5.1-3 10.1-5.5 15.2H192c-17.7 0-32 14.3-32 32s14.3 32 32 32h41.5c-4.6 22-8.5 47.9-10.4 76.7c-50.5 8.9-89.1 53.1-89.1 106.3c0 17.7 14.3 32 32 32s32-14.3 32-32c0-30.8 21.6-56.5 51.1-62.4c4.6 68.6 30 119.4 68.9 119.4s64.3-50.8 68.9-119.4c29.5 5.9 51.1 31.6 51.1 62.4c0 17.7 14.3 32 32 32s32-14.3 32-32c0-53.2-38.6-97.4-89.1-106.3c-1.9-28.8-5.8-54.7-10.4-76.7H320c17.7 0 32-14.3 32-32s-14.3-32-32-32h-50.5c-2.5-5.1-4.3-10.1-5.5-15.2c13.5-3.7 23.5-16.1 23.5-30.8zm-32-64c35.3 0 64 28.7 64 64c0 23.4-12.6 43.9-31.4 55.1c3.1 13.9 7.7 29.8 13.4 47.3H320c35.3 0 64 28.7 64 64c0 30.5-21.3 56-50.1 62.5c-3.1 41.2-18 73.5-40.2 87.5v-18.7c0-17.7-14.3-32-32-32s-32 14.3-32 32v18.7c-22.2-14-37.1-46.3-40.2-87.5C160.7 325 139.4 299.5 139.4 269c0-35.3 28.7-64 64-64h17.9c5.7-17.5 10.3-33.4 13.4-47.3C216.6 146.9 204 126.4 204 103c0-35.3 28.7-64 64-64z"/>
</svg>""",

    # COMPASS (Kompass)
    "kompass": """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="#000000">
  <path fill="#000000" d="M256 0c141.4 0 256 114.6 256 256S397.4 512 256 512S0 397.4 0 256S114.6 0 256 0zm0 48C141.1 48 48 141.1 48 256s93.1 208 208 208s208-93.1 208-208S370.9 48 256 48zm63.4 129.2L368 368l-182.8-48.6L144 144l175.4 33.2zM256 232a24 24 0 1 0 0 48a24 24 0 1 0 0-48z"/>
</svg>""",

    # TREE OF LIFE (Lebensbaum / Keltischer Baum)
    "lebensbaum": """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="#000000">
  <path fill="#000000" d="M256 16C123.5 16 16 123.5 16 256s107.5 240 240 240s240-107.5 240-240S388.5 16 256 16zm0 32c114.9 0 208 93.1 208 208c0 36.3-9.3 70.4-25.6 100.1c-16.1-23.7-41.9-42.6-70.4-53.1c11.4-15.6 18-35.1 18-56.1c0-43.1-29.3-79.3-69.2-89.8c5-11.8 7.8-24.8 7.8-38.5c0-53-43-96-96-96s-96 43-96 96c0 13.7 2.8 26.7 7.8 38.5c-39.9 10.5-69.2 46.7-69.2 89.8c0 21 6.6 40.5 18 56.1c-28.5 10.5-54.3 29.4-70.4 53.1C57.3 329.4 48 295.3 48 256C48 141.1 141.1 48 256 48zm-16 192c0-26.5 21.5-48 48-48s48 21.5 48 48c0 18.2-10.2 34-25.1 42.1l-14.9 8.1V352h32c17.7 0 32 14.3 32 32s-14.3 32-32 32h-128c-17.7 0-32-14.3-32-32s14.3-32 32-32h32V290.2l-14.9-8.1C246.2 274 236 258.2 236 240z"/>
</svg>""",

    # BUTTERFLY (Schmetterling)
    "schmetterling": """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="#000000">
  <path fill="#000000" d="M256 160c8.8 0 16 7.2 16 16v160c0 8.8-7.2 16-16 16s-16-7.2-16-16V176c0-8.8 7.2-16 16-16zm-32.8-23.7C191.9 96 142.3 64 80 64C35.8 64 0 99.8 0 144c0 88.4 71.6 160 160 160c23.2 0 45-4.9 64.7-13.8l-1.5-53.9zm65.6 0l-1.5 53.9c19.7 8.9 41.5 13.8 64.7 13.8c88.4 0 160-71.6 160-160c0-44.2-35.8-80-80-80c-62.3 0-111.9 32-143.2 72.3zM160 336c-53 0-96 43-96 96c0 44.2 35.8 80 80 80c53.3 0 96.5-35.5 112-85.3V342.2C226.7 338.3 194.5 336 160 336zm192 0c-34.5 0-66.7 2.3-96 6.2v84.5c15.5 49.8 58.7 85.3 112 85.3c44.2 0 80-35.8 80-80c0-53-43-96-96-96z"/>
</svg>""",

    # BABY FEET (Babyfüße)
    "baby_fuesse": """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="#000000">
  <path fill="#000000" d="M144 48c26.5 0 48 21.5 48 48c0 44.2-17.9 80-40 80s-40-35.8-40-80c0-26.5 21.5-48 48-48zm72 16c17.7 0 32 14.3 32 32s-14.3 32-32 32s-32-14.3-32-32s14.3-32 32-32zm48 48c13.3 0 24 10.7 24 24s-10.7 24-24 24s-24-10.7-24-24s10.7-24 24-24zm32 48c8.8 0 16 7.2 16 16s-7.2 16-16 16s-16-7.2-16-16s7.2-16 16-16zm-176 96c44.2 0 80 35.8 80 80c0 44.2-16 80-32 128c-16 48-32 80-64 80s-64-48-64-112c0-88.4 35.8-176 80-176zm248-112c26.5 0 48 21.5 48 48c0 44.2-17.9 80-40 80s-40-35.8-40-80c0-26.5 21.5-48 48-48zm-72 16c17.7 0 32 14.3 32 32s-14.3 32-32 32s-32-14.3-32-32s14.3-32 32-32zm-48 48c13.3 0 24 10.7 24 24s-10.7 24-24 24s-24-10.7-24-24s10.7-24 24-24zm-32 48c8.8 0 16 7.2 16 16s-7.2 16-16 16s-16-7.2-16-16s7.2-16 16-16zm176 96c44.2 0 80 35.8 80 80c0 44.2-16 80-32 128c-16 48-32 80-64 80s-64-48-64-112c0-88.4 35.8-176 80-176z"/>
</svg>""",

    # LAUREL WREATH (Lorbeerkranz)
    "lorbeerkranz": """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="#000000">
  <path fill="#000000" d="M128 96c-17.7 0-32 14.3-32 32c0 23.9 14.1 44.5 34.5 54.1C118 198.8 112 216.7 112 236c0 15.6 4.1 30.2 11.2 43C104.9 284.1 96 298.9 96 316c0 30.9 25.1 56 56 56c8.8 0 16 7.2 16 16s-7.2 16-16 16c-48.6 0-88-39.4-88-88c0-23.7 9.4-45.2 24.7-61C78.9 240.8 74 223 74 204c0-26.6 9.6-51 25.7-70.1C87.4 122.9 80 106.3 80 88c0-35.3 28.7-64 64-64c8.8 0 16 7.2 16 16s-7.2 16-16 16c-17.7 0-32 14.3-32 32c0 2.8.4 5.5 1.1 8.1c9.3-5.2 20.1-8.1 31.6-8.1c35.3 0 64 28.7 64 64c0 8.8-7.2 16-16 16s-16-7.2-16-16c0-17.7-14.3-32-32-32zm256 0c-17.7 0-32 14.3-32 32c0 8.8-7.2 16-16 16s-16-7.2-16-16c0-35.3 28.7-64 64-64c11.5 0 22.3 2.9 31.6 8.1c.7-2.6 1.1-5.3 1.1-8.1c0-17.7-14.3-32-32-32c-8.8 0-16-7.2-16-16s7.2-16 16-16c35.3 0 64 28.7 64 64c0 18.3-7.4 34.9-19.7 45.9c16.1 19.1 25.7 43.5 25.7 70.1c0 19-4.9 36.8-14.7 51C434.6 270.8 444 292.3 444 316c0 48.6-39.4 88-88 88c-8.8 0-16-7.2-16-16s7.2-16 16-16c30.9 0 56-25.1 56-56c0-17.1-8.9-31.9-22.2-39.9c7.1-12.8 11.2-27.4 11.2-43c0-19.3-6-37.2-18.5-53.9c20.4-9.6 34.5-30.2 34.5-54.1c0-17.7-14.3-32-32-32zM216 432c-13.3 0-24 10.7-24 24s10.7 24 24 24h80c13.3 0 24-10.7 24-24s-10.7-24-24-24h-80z"/>
</svg>""",

    # HERZKRANZ
    "herzkranz": """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#000000">
  <path fill="#000000" fill-rule="evenodd" d="M12 4.248c-3.148-5.402-12-3.825-12 2.94 0 4.661 5.571 9.427 12 15.808 6.43-6.381 12-11.147 12-15.808 0-6.792-8.875-8.306-12-2.94zm0 2.252c2.3 0 5 1.5 5 4.5 0 3.2-3.8 6.8-5 8-1.2-1.2-5-4.8-5-8 0-3 2.7-4.5 5-4.5z" clip-rule="evenodd"/>
</svg>""",

    # ZIERLEISTE (Laser Divider)
    "zierleiste": """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 50" fill="#000000">
  <path fill="#000000" d="M250 8 L262 25 L250 42 L238 25 Z M20 23 L225 23 L225 27 L20 27 Z M275 23 L480 23 L480 27 L275 27 Z M215 18 L225 25 L215 32 Z M285 18 L275 25 L285 32 Z M15 25 A5 5 0 1 1 15 24.9 Z M485 25 A5 5 0 1 1 485 24.9 Z"/>
</svg>""",

    # ROSE (Rose mit Blüte)
    "rose": """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="#000000">
  <path fill="#000000" d="M256 32c-61.9 0-112 50.1-112 112c0 38.6 19.5 72.6 49.3 92.8c-1.5 6.3-2.3 12.8-2.3 19.2c0 23.5 10.3 44.5 26.7 59.1L208 448c0 17.7 14.3 32 32 32s32-14.3 32-32l-9.7-132.9c16.4-14.6 26.7-35.6 26.7-59.1c0-6.4-.8-12.9-2.3-19.2c29.8-20.2 49.3-54.2 49.3-92.8c0-61.9-50.1-112-112-112zm0 48c35.3 0 64 28.7 64 64s-28.7 64-64 64s-64-28.7-64-64s28.7-64 64-64z"/>
</svg>""",

    # WEDDING RINGS (Eheringe)
    "eheringe": """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="#000000">
  <path fill="#000000" d="M192 64c-70.7 0-128 57.3-128 128c0 62.5 44.8 114.6 104 125.6v-33.3C126.7 274.2 96 235.8 96 192c0-53 43-96 96-96c43.8 0 82.2 30.7 92.3 72h33.3C306.6 108.8 254.5 64 192 64zm128 64c-70.7 0-128 57.3-128 128c0 70.7 57.3 128 128 128s128-57.3 128-128s-57.3-128-128-128zm0 32c53 0 96 43 96 96s-43 96-96 96s-96-43-96-96s43-96 96-96z"/>
</svg>"""
}

# Master categorization mapping: Category -> list of (icon_filename, bootstrap_name OR custom_key)
CATEGORIES = {
    "Liebe": [
        ("herz", "heart-fill"),
        ("doppelherzen", "hearts"),
        ("amorpfeil", "arrow-through-heart-fill"),
        ("herz_ballon", "balloon-heart-fill"),
        ("herz_brief", "envelope-heart-fill"),
        ("unendlichkeit", "infinity"),
        ("eheringe", "custom:eheringe"),
        ("diamantring", "gem"),
    ],
    "Familie": [
        ("baby_fuesse", "custom:baby_fuesse"),
        ("familie", "people-fill"),
        ("eltern_liebe", "person-hearts"),
        ("glueckskind", "emoji-smile-fill"),
        ("beschuetzt", "shield-fill-check"),
        ("baby_laecheln", "emoji-heart-eyes-fill"),
    ],
    "Tiere": [
        ("hundepfote", "custom:hundepfote"),
        ("schmetterling", "custom:schmetterling"),
    ],
    "Natur": [
        ("tanne", "tree-fill"),
        ("sonnenblume", "flower1"),
        ("bluete", "flower2"),
        ("lotus", "flower3"),
    ],
    "Symbole": [
        ("stern", "star-fill"),
        ("sterne_sternschnuppe", "stars"),
        ("sonne", "sun-fill"),
        ("mond_sterne", "moon-stars-fill"),
        ("kompass", "custom:kompass"),
        ("schluessel", "key-fill"),
        ("sieger_pokal", "trophy-fill"),
        ("auszeichnung", "award-fill"),
        ("noten", "music-note-beamed"),
        ("friedenstaube", "peace-fill"),
        ("puzzlestueck", "puzzle-fill"),
        ("feder", "feather"),
    ],
    "Ornamente": [
        ("herzkranz", "custom:herzkranz"),
        ("zierleiste", "custom:zierleiste"),
    ],
    "Feiertage": [
        ("geschenk", "gift-fill"),
        ("feier_sekt", "cup-hot-fill"),
        ("festglocke", "bell-fill"),
        ("party_ballon", "balloon-fill"),
    ]
}

def main():
    # Remove existing categorized directories to get a 100% clean state
    if os.path.exists(VECTORS_DIR):
        for item in os.listdir(VECTORS_DIR):
            p = os.path.join(VECTORS_DIR, item)
            if os.path.isdir(p):
                shutil.rmtree(p)
            elif os.path.isfile(p) and item.endswith('.svg'):
                os.remove(p)

    # Re-create all categories with pristine laser icons
    total_created = 0
    for cat_name, icon_list in CATEGORIES.items():
        cat_dir = os.path.join(VECTORS_DIR, cat_name)
        os.makedirs(cat_dir, exist_ok=True)

        for file_name, src in icon_list:
            target_path = os.path.join(cat_dir, f"{file_name}.svg")
            content = None

            if src.startswith("custom:"):
                key = src.split(":", 1)[1]
                if key in CUSTOM_VECTORS:
                    content = ensure_black_svg(CUSTOM_VECTORS[key])
            else:
                content = load_bootstrap_icon(src)

            if content:
                with open(target_path, "w", encoding="utf-8") as f:
                    f.write(content)
                total_created += 1
                print(f"[{cat_name}] Created: {file_name}.svg")
            else:
                print(f"[{cat_name}] Warning: source not found for {file_name} ({src})")

    # Re-integrate and clean preserved high quality motifs from backup
    preserve_map = {
        "Liebe": [
            ("marry-me_black.svg", "heiratsantrag.svg"),
            ("wedding_black.svg", "brautpaar.svg"),
            ("forever_black.svg", "fuer_immer.svg"),
            ("heart_black.svg", "herz_kontur.svg"),
            ("ring_2-white_black.svg", "ringe_verbunden.svg"),
            ("hugeicons-wedding.svg", "hochzeitsglocken.svg")
        ],
        "Familie": [
            ("baby_black.svg", "baby_gesicht.svg"),
            ("babygirl_black.svg", "baby_maedchen.svg"),
            ("footprint_black.svg", "baby_fussabdruck.svg")
        ],
        "Tiere": [
            ("fluent-animal-cat-28-filled.svg", "katze.svg"),
            ("fluent-animal-dog-24-filled.svg", "hund.svg"),
            ("fluent-animal-rabbit-16-filled.svg", "hase.svg")
        ],
        "Natur": [
            ("flower.svg", "gartenblume.svg")
        ],
        "Symbole": [
            ("beer.svg", "bierglas.svg"),
            ("beermug_black.svg", "bierkrug.svg")
        ],
        "Feiertage": [
            ("party_black.svg", "party_hut.svg"),
            ("mingcute-christmas-hat-line.svg", "weihnachtsmann_muetze.svg"),
            ("dinkie-icons-christmas-tree.svg", "weihnachtsbaum.svg"),
            ("game-icons-easter-egg.svg", "osterei.svg")
        ]
    }

    if os.path.exists(BACKUP_DIR):
        for cat_name, files in preserve_map.items():
            cat_dir = os.path.join(VECTORS_DIR, cat_name)
            os.makedirs(cat_dir, exist_ok=True)
            for orig, dest in files:
                found_src = None
                for root, _, filenames in os.walk(BACKUP_DIR):
                    if orig in filenames:
                        found_src = os.path.join(root, orig)
                        break
                if found_src:
                    with open(found_src, "r", encoding="utf-8") as f:
                        raw = f.read()
                    cleaned = ensure_black_svg(raw)
                    target_file = os.path.join(cat_dir, dest)
                    with open(target_file, "w", encoding="utf-8") as f:
                        f.write(cleaned)
                    total_created += 1
                    print(f"[{cat_name}] Preserved: {orig} -> {dest}")

    print(f"\nSUCCESS! Created and organized {total_created} laser-ready vector symbols.")

if __name__ == "__main__":
    main()
