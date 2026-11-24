# Viñetas de humor innovAI (estilo viñeta de prensa)

Aprobado por Pablo el 24/11/2026. Ejemplos aprobados: ejemplo-*.jpg.

## Cómo generar
1. `npm i playwright && npx playwright install chromium` (en esta carpeta).
2. Edita el objeto `v` al final de `forges.js`: una entrada por viñeta (nombre → código de dibujo).
   Funciones: `head(titular)` arriba; `tipo(x,y,escala,{...})` personaje cabezón de nariz grande
   (hair: scribble|bald|perm, hc, cap, body, apron, coat, glasses, tie, mous, brows: up|angry|sad,
   mouth: open|smile|wavy, sleepy, left/right: down|up|front|hip|point|chin, noLegs, short; devuelve {hl,hr} = manos);
   `robotito(x,y,escala,{tie,eyes:'sad'|'stern',noLegs})`; `say(x,y,texto,tamaño,px,py)` bocadillo con pico hacia (px,py);
   `RS/RW/E/EW/L/P/PW/C` formas; `footer('Medio, dd/mm/aaaa')`.
3. `node forges.js` genera <nombre>.jpg (1080×1350). Revisa cada imagen: bocadillos sin tapar caras ni otros textos, nada fuera del marco.

## Reglas
- Noticia real y verificada (fuente y fecha en el pie). Solo la reacción es broma.
- Una sola escena, 2 frases en bocadillo como mucho, frases cortas.
- Personajes propios: no copiar personajes, firma ni coletillas de dibujantes reales. No mencionar «pueblo».
- Sin política, religión ni burlas a personas concretas. Sin precios. Una sola llamada a la acción.
