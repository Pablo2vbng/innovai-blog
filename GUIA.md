# Guía editorial del blog de innovAI (v2, desde el 04/11/2026)

## Objetivo
Cada artículo tiene que atraer a alguien que **puede ser cliente** (pymes, tiendas y autónomos de la Comunitat Valenciana y España) y llevarle a una página de servicio o a la tienda NFC. Nada de noticias genéricas en las que competimos con periódicos.

## Plan semanal
- Cada lunes a primera hora se analiza la competencia y se guarda el plan en `seo/AAAA-Www.md` (semana ISO). Los artículos de esa semana salen de ese plan: palabra clave, intención, qué tienen los 3-5 primeros resultados orgánicos y qué hay que hacer para superarlos.
- Dos artículos por semana: lunes y jueves. Si el plan indica actualizar un artículo existente (posiciones 5-20 en Search Console), se hace en lugar de uno nuevo o además, sin pasar de dos piezas.

## Temas (clústeres)
1. **Páginas web** → /paginas-web-para-pymes.html (precio de una web, qué debe tener, web vs. redes, web para [sector], diseño web en [localidad]).
2. **Reseñas y Google** → /google-business-seo-local.html y /tienda.html (cómo conseguir reseñas, tarjetas y soportes NFC, ficha de Google Business, aparecer en «cerca de mí»).
3. **Tarjetas y soportes NFC** → /tienda.html (cómo funcionan, NFC vs. QR, tarjeta de visita NFC, ideas por tipo de negocio).
4. **Agente de IA / WhatsApp** → /agente-ia-whatsapp.html (chatbot para negocio, responder fuera de horario, citas automáticas).
5. **Automatizaciones y registro horario** → /automatizaciones-para-empresas.html y /registro-horario-digital.html.
Prioridad actual: 1, 2 y 3 (presencia en internet y NFC para negocios locales).

## SEO
- Una palabra clave principal por artículo, elegida en el plan semanal: con búsquedas reales, intención informativa o comercial, y que una web pequeña pueda ganar (resultados sin grandes portales o con contenido flojo). Se aceptan variantes locales («… en Xàtiva», «… en Valencia») cuando el plan lo indique.
- La palabra clave va en el título (30-70 caracteres), en la descripción (110-165), en el slug, en el primer párrafo y en al menos un `<h2>`. Variantes y sinónimos de forma natural, sin repetir por repetir.
- **Responder a la intención en el primer párrafo** (la respuesta directa, después el detalle).
- Cubrir todo lo que cubren los 3 primeros resultados y añadir algo que no tengan: un ejemplo local, una tabla, pasos concretos, errores comunes, una comparativa honesta.
- 1.200-2.000 palabras si la competencia es larga; nunca relleno.
- Estructura: `<h2>`/`<h3>` claros, párrafos cortos, al menos una lista y, cuando aporte, una tabla. Sin `<h1>` en el cuerpo.
- 4-6 preguntas frecuentes reales (campo `faq`), las que aparecen en «Otras preguntas de los usuarios» o en la competencia, respondidas en 1-3 frases.
- Enlaces internos: 2-3 a páginas de servicio/tienda con texto descriptivo, y 1 a otro artículo del blog si existe.
- Fecha y datos actualizados; si se citan cifras o normas, con fuente.

## Reglas
- Español de España, tono cercano y claro, sin tecnicismos sin explicar.
- Todo dato con fuente (campo `sources`, mínimo 2 fuentes fiables). Nada inventado. Nada copiado de la competencia: se analiza y se escribe algo mejor con palabras propias.
- Sin precios de servicios de innovAI (los de la tienda NFC sí se pueden mencionar como "desde" si aporta, sin inventar). La llamada a la acción la pone la plantilla; el texto no añade otra.
- No mencionar CV Tools. El agente de IA de innovAI se llama Inés.
- Los borradores (`borradores/`, con `body_html`) no se suben; solo `posts/`, `manifest.json` y `seo/`.
