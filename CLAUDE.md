# Tostela: tienda de café de especialidad

Tienda online de ejemplo para un tutorial de YouTube: WordPress y WooCommerce en Docker, publicada en internet con un
túnel de Cloudflare. La marca y los productos son inventados, y la tienda funciona en modo de prueba.

## Lo que ya hay

- `contenido/productos.json`: el catálogo (cafés con variaciones de molienda y peso, y accesorios).
- `contenido/paginas/`: los textos de las páginas (legales, nuestra historia y contacto).
- `assets/img/`: las fotos, generadas con IA (sus prompts están en `contenido/imagenes.json`).
- `assets/fonts/`: Fraunces e Inter, para servirlas desde la propia tienda.
- `.env`: ya trae la clave de Resend (`RESEND_API_KEY`) y el remitente (`CORREO_REMITENTE`).

## Reglas

- Esta sesión se graba para el vídeo: nunca enseñes claves ni contraseñas. No leas ni imprimas `.env`; las contraseñas
  nuevas, genéralas directamente dentro de `.env`, sin mostrarlas.
- En este equipo hay contenedores de otros proyectos: usa siempre `docker compose` desde esta carpeta y no toques
  nada fuera de ella.
- La tienda escucha en `127.0.0.1:8086` (el 8085 está ocupado).
- El dominio es `tostela.c2developers.com`, y `cloudflared` ya tiene sesión en este equipo.
- Las compras de prueba las hago yo, en local: no metas tarjetas en ningún formulario.
- Todo en español de España: textos, euros e IVA de España.
- Cuando cites ficheros, usa rutas relativas a esta carpeta.
- Commits pequeños, con el mensaje en español.
