"""Generuje SVG verzie loga Petrolejka s nápisom prevedeným na krivky.

„Petro“ je slangový názov Liptovského Petra, preto je v nápise zvýraznené.

Použitie (v priečinku s fontom Fraunces.ttf z Google Fonts, OFL):
  python3 -m venv venv && venv/bin/pip install fonttools resvg-py
  venv/bin/python make-logo.py <výstupný priečinok> [variant]
PNG sa renderujú cez resvg_py.svg_to_bytes (ImageMagick v ddev nevykreslí stroke).
"""
import io
import sys
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.pens.boundsPen import BoundsPen

OUT = sys.argv[1]
VARIANT = sys.argv[2] if len(sys.argv) > 2 else "a"  # zvolený variant A

BLUE, BLUE_DARK, BLUE_SOFT = "#1d3f8f", "#0f2557", "#6f8fd6"
YELLOW, PALE, WHITE = "#f4c20d", "#fff6cf", "#ffffff"


def load(path, axes):
    font = instancer.instantiateVariableFont(TTFont(path), axes)
    buf = io.BytesIO()
    font.save(buf)
    buf.seek(0)
    return TTFont(buf)


FONTS = {
    "bold": load("Fraunces.ttf", {"wght": 800, "opsz": 72, "SOFT": 0, "WONK": 0}),
    "light": load("Fraunces.ttf", {"wght": 300, "opsz": 72, "SOFT": 0, "WONK": 0}),
    "semi": load("Fraunces.ttf", {"wght": 600, "opsz": 72, "SOFT": 0, "WONK": 0}),
}


def run(font, text, size, x, y, tracking=0.0):
    """Vykreslí text na baseline y. Vráti (path d, posun x, bounds)."""
    gs = font.getGlyphSet()
    cmap = font.getBestCmap()
    s = size / font["head"].unitsPerEm
    pen = SVGPathPen(gs)
    bp = BoundsPen(gs)
    cx = 0.0
    for i, ch in enumerate(text):
        g = gs[cmap[ord(ch)]]
        m = (s, 0, 0, -s, x + cx, y)
        g.draw(TransformPen(pen, m))
        g.draw(TransformPen(bp, m))
        cx += g.width * s + (tracking * size if i < len(text) - 1 else 0)
    return pen.getCommands(), cx, bp.bounds


# Varianty nápisu: zoznam (text, font, rola farby).
# Roly: "petro" = zvýraznená časť, "rest" = zvyšok.
WORDMARKS = {
    # A: Petro tučné modré, lejka tenké tmavé.
    "a": [("Petro", "bold", "petro"), ("lejka", "light", "rest")],
    # B: rovnaký rez, Petro modré a podčiarknuté žltou „žiarou“.
    "b": [("Petro", "semi", "petro"), ("lejka", "semi", "rest")],
    # C: PETRO kapitálkami, lejka gemenkami.
    "c": [("PETRO", "bold", "petro"), ("lejka", "light", "rest")],
}


def palette(scheme):
    if scheme == "color":
        return {"petro": BLUE, "rest": BLUE_DARK, "lamp": BLUE, "glow": PALE, "glow_op": 1, "mark": YELLOW}
    return {"petro": YELLOW, "rest": WHITE, "lamp": WHITE, "glow": PALE, "glow_op": 1, "mark": YELLOW}


def wordmark(x, y, size, scheme):
    """Vráti (svg, bounds) nápisu s baseline y."""
    col = palette(scheme)
    parts, cx = [], 0.0
    xmin = ymin = 1e9
    xmax = ymax = -1e9
    petro_box = None
    for text, font, role in WORDMARKS[VARIANT]:
        d, w, b = run(FONTS[font], text, size, x + cx, y)
        parts.append(f'<path d="{d}" fill="{col[role]}"/>')
        if role == "petro":
            petro_box = b
        xmin, ymin = min(xmin, b[0]), min(ymin, b[1])
        xmax, ymax = max(xmax, b[2]), max(ymax, b[3])
        cx += w
    if VARIANT == "b" and petro_box:
        # Žltý pás pod „Petro“ – ako svetlo petrolejky.
        h = size * 0.13
        bar = (f'<rect x="{petro_box[0]:.1f}" y="{y + size * 0.06:.1f}" width="{petro_box[2] - petro_box[0]:.1f}" '
               f'height="{h:.1f}" rx="{h / 2:.1f}" fill="{col["mark"]}"/>')
        parts.insert(0, bar)
        ymax = max(ymax, y + size * 0.06 + h)
    return "\n  ".join(parts), (xmin, ymin, xmax, ymax)


def lamp(scheme):
    """Petrolejka vo viewBoxe 64×64 (obsah cca x 15–49, y 4–58)."""
    c = palette(scheme)
    s = c["lamp"]
    return f'''<path d="M27 42c-5.5-3-8-8-8-13.5 0-6.2 3.6-10.6 8-12.8V11h10v4.7c4.4 2.2 8 6.6 8 12.8 0 5.5-2.5 10.5-8 13.5z" fill="{c['glow']}" fill-opacity="{c['glow_op']}"/>
    <path d="M32 18.5c-3.4 4.4-5.6 8-5.6 11.2a5.6 5.6 0 0 0 11.2 0c0-3.2-2.2-6.8-5.6-11.2z" fill="{YELLOW}"/>
    <path d="M32 25c-1.5 2-2.5 3.6-2.5 5a2.5 2.5 0 0 0 5 0c0-1.4-1-3-2.5-5z" fill="{PALE}"/>
    <path d="M27 42c-5.5-3-8-8-8-13.5 0-6.2 3.6-10.6 8-12.8V11h10v4.7c4.4 2.2 8 6.6 8 12.8 0 5.5-2.5 10.5-8 13.5" fill="none" stroke="{s}" stroke-width="2.6" stroke-linejoin="round"/>
    <path d="M25.5 11h13" stroke="{s}" stroke-width="2.6" stroke-linecap="round"/>
    <path d="M29 7.5a3 3 0 0 1 6 0" fill="none" stroke="{s}" stroke-width="2.2" stroke-linecap="round"/>
    <rect x="25" y="41.5" width="14" height="5" rx="1.2" fill="{s}"/>
    <path d="M39 44h3.5a1.6 1.6 0 0 1 0 3.2H39z" fill="{s}"/>
    <path d="M21 48h22c3 0 5.5 2.2 5.5 5s-2.5 5-5.5 5H21c-3 0-5.5-2.2-5.5-5s2.5-5 5.5-5z" fill="{s}"/>'''


LAMP_BOX = (15, 4.5, 49, 58)  # x0, y0, x1, y1


def lamp_group(x, y, h, scheme):
    k = h / (LAMP_BOX[3] - LAMP_BOX[1])
    return (f'<g transform="translate({x - LAMP_BOX[0] * k:.2f} {y - LAMP_BOX[1] * k:.2f}) scale({k:.4f})">\n    '
            f'{lamp(scheme)}\n  </g>'), (LAMP_BOX[2] - LAMP_BOX[0]) * k


def svg(w, h, body):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w:.0f} {h:.0f}" role="img" '
            f'aria-label="Petrolejka – Liptovský Peter">\n  {body}\n</svg>\n')


def horizontal(scheme):
    pad, lamp_h, size = 24, 220, 150
    lamp_svg, lamp_w = lamp_group(pad, pad, lamp_h, scheme)
    tx = pad + lamp_w + 34
    # najprv nanečisto kvôli rozmerom
    _, nb = wordmark(0, 0, size, scheme)
    # Nápis opticky na stred lampy (výška verzálok/„P“ bez dĺžňov).
    top = pad + (lamp_h - (nb[3] - nb[1])) / 2
    base = top - nb[1]
    wm, nb2 = wordmark(tx - nb[0], base, size, scheme)
    return svg(nb2[2] + pad, lamp_h + 2 * pad, f'{lamp_svg}\n  {wm}')


def stacked(scheme):
    pad, lamp_h, size = 32, 280, 128
    _, nb = wordmark(0, 0, size, scheme)
    name_w = nb[2] - nb[0]
    lamp_w = (LAMP_BOX[2] - LAMP_BOX[0]) * lamp_h / (LAMP_BOX[3] - LAMP_BOX[1])
    width = max(name_w, lamp_w) + 2 * pad
    cx = width / 2
    lamp_svg, _ = lamp_group(cx - lamp_w / 2, pad, lamp_h, scheme)
    base = pad + lamp_h + 34 - nb[1]
    wm, nb2 = wordmark(cx - name_w / 2 - nb[0], base, size, scheme)
    return svg(width, nb2[3] + pad, f'{lamp_svg}\n  {wm}')


def icon(scheme):
    return svg(64, 64, lamp(scheme)).replace('viewBox="0 0 64 64"', 'viewBox="0 0 64 64"')


def badge():
    """Ikona v kruhu – profilovka na sociálne siete."""
    k = 0.78
    cx, cy = (LAMP_BOX[0] + LAMP_BOX[2]) / 2, (LAMP_BOX[1] + LAMP_BOX[3]) / 2
    return svg(64, 64, f'<circle cx="32" cy="32" r="32" fill="{BLUE_DARK}"/>\n  '
                       f'<g transform="translate({32 - cx * k:.2f} {32 - cy * k:.2f}) scale({k})">\n    {lamp("inverse")}\n  </g>')


def podcast_cover():
    """Obal podcastu 3000×3000 (Apple: 1400–3000 px, štvorec)."""
    size = 3000
    lamp_h = 1250
    lamp_svg, lamp_w = lamp_group((size - (LAMP_BOX[2] - LAMP_BOX[0]) * lamp_h / (LAMP_BOX[3] - LAMP_BOX[1])) / 2, 380, lamp_h, "inverse")
    name_size = 470
    _, nb = wordmark(0, 0, name_size, "inverse")
    base = 380 + lamp_h + 200 - nb[1]
    wm, nb2 = wordmark((size - (nb[2] - nb[0])) / 2 - nb[0], base, name_size, "inverse")
    _, _, pb = run(FONTS["semi"], "PODCAST", 170, 0, 0, 0.3)
    pod_d, _, _ = run(FONTS["semi"], "PODCAST", 170, (size - (pb[2] - pb[0])) / 2 - pb[0], nb2[3] + 140 - pb[1], 0.3)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {size} {size}">\n'
            f'  <rect width="{size}" height="{size}" fill="{BLUE_DARK}"/>\n'
            f'  <circle cx="{size / 2}" cy="{380 + lamp_h * 0.42}" r="900" fill="{BLUE}" fill-opacity="0.55"/>\n'
            f'  {lamp_svg}\n  {wm}\n  <path d="{pod_d}" fill="{YELLOW}"/>\n</svg>\n')


def write(name, content):
    with open(f"{OUT}/{name}", "w") as f:
        f.write(content)


for scheme in ("color", "inverse"):
    write(f"petrolejka-logo-horizontal-{scheme}.svg", horizontal(scheme))
    write(f"petrolejka-logo-stacked-{scheme}.svg", stacked(scheme))
    write(f"petrolejka-icon-{scheme}.svg", icon(scheme))
write("petrolejka-icon-badge.svg", badge())
write("petrolejka-podcast-cover.svg", podcast_cover())
print("ok", VARIANT)
