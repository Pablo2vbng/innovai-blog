#!/usr/bin/env python3
"""Gestión del blog de innovAI (innovai.es/blog).

  python3 tools/blog.py nuevo borradores/<slug>.json   # crea posts/<slug>.json y lo añade como 'pendiente' (+24 h)
  python3 tools/blog.py aprobar <slug>                 # se publica en la siguiente sincronización
  python3 tools/blog.py rechazar <slug>
  python3 tools/blog.py retirar <slug>                 # quita de la web un artículo ya publicado
  python3 tools/blog.py lista

El borrador es un JSON con: slug, title, description, category ("Pymes" | "Actualidad"),
keywords [..], sources [{title,url}], faq [{q,a}] y body_html (HTML del cuerpo, sin <h1>).
En posts/ el cuerpo se guarda en base64 (body_b64) para que GitHub no compita en Google con la web.
"""
import base64, datetime as dt, json, re, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
MAN = ROOT / "manifest.json"
NOW = lambda: dt.datetime.now(dt.timezone.utc).replace(microsecond=0)

def load():
    return json.loads(MAN.read_text(encoding="utf-8"))

def save(m):
    MAN.write_text(json.dumps(m, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

def find(m, slug):
    for it in m["posts"]:
        if it["slug"] == slug:
            return it
    sys.exit(f"No existe {slug}")

def nuevo(path, horas=24):
    d = json.loads(Path(path).read_text(encoding="utf-8"))
    slug = d["slug"]
    assert re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", slug) and len(slug) <= 90, "slug no válido"
    assert d["category"] in ("Pymes", "Actualidad"), "categoría no válida"
    assert 30 <= len(d["title"]) <= 70, f"título de {len(d['title'])} caracteres (30-70)"
    assert 110 <= len(d["description"]) <= 165, f"descripción de {len(d['description'])} caracteres (110-165)"
    assert d.get("sources"), "faltan fuentes"
    body = d.pop("body_html")
    assert "<h1" not in body.lower(), "el cuerpo no lleva <h1>"
    m = load()
    old = next((it for it in m["posts"] if it["slug"] == slug), None)
    rev = (old["rev"] + 1) if old else 1
    post = {**d, "rev": rev, "body_b64": base64.b64encode(body.encode("utf-8")).decode()}
    (ROOT / "posts" / f"{slug}.json").write_text(json.dumps(post, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    at = (NOW() + dt.timedelta(hours=horas)).isoformat()
    item = {"slug": slug, "title": d["title"], "description": d["description"], "category": d["category"],
            "date": NOW().date().isoformat(), "publish_at": at, "status": "pendiente", "rev": rev}
    if old:
        old.update(item)
    else:
        m["posts"].insert(0, item)
    save(m)
    print(f"{slug}: pendiente, se publica sola el {at} si no se rechaza")

def estado(slug, st):
    m = load(); it = find(m, slug)
    it["status"] = st
    if st == "aprobado":
        it["publish_at"] = NOW().isoformat()
    save(m)
    print(f"{slug}: {st}")

def lista():
    for it in load()["posts"]:
        print(f'{it["status"]:10} {it["publish_at"][:16]}  {it["category"]:10} {it["slug"]}')

if __name__ == "__main__":
    a = sys.argv[1:]
    if not a: sys.exit(__doc__)
    cmd = a[0]
    if cmd == "nuevo": nuevo(a[1], float(a[2]) if len(a) > 2 else 24)
    elif cmd == "aprobar": estado(a[1], "aprobado")
    elif cmd == "rechazar": estado(a[1], "rechazado")
    elif cmd == "retirar": estado(a[1], "retirado")
    elif cmd == "lista": lista()
    else: sys.exit(__doc__)
