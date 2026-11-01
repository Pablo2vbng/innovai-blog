# Blog de innovAI

Contenido del blog de https://innovai.es/blog/. La web lee este repositorio cada 30 minutos y publica lo que toca.

## Cómo funciona

1. Tres veces por semana se escribe un artículo (lunes y miércoles para pymes, viernes de actualidad tecnológica).
2. Entra en `manifest.json` con estado `pendiente` y fecha de publicación +24 h. Pablo recibe el enlace de vista previa.
3. Si responde «sí», pasa a `aprobado` y sale en la siguiente sincronización. Si responde «no», pasa a `rechazado` y no se publica. Sin respuesta, se publica solo a las 24 h.

## Estados

| Estado | Qué pasa |
|---|---|
| `pendiente` | Se publica al llegar `publish_at` |
| `aprobado` | Se publica ya |
| `rechazado` | No se publica |
| `retirado` | Se quita de la web si ya estaba |

## Herramienta

```
python3 tools/blog.py nuevo borradores/<slug>.json
python3 tools/blog.py aprobar <slug>
python3 tools/blog.py rechazar <slug>
python3 tools/blog.py retirar <slug>
python3 tools/blog.py lista
```

Las normas de redacción están en `GUIA.md`.
