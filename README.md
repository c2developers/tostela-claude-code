# Tostela

Tienda online de ejemplo de café de especialidad, hecha con WordPress y WooCommerce en Docker para un tutorial de
YouTube. La marca y los productos son inventados, las fotos están generadas con IA y la tienda funciona en modo de prueba.

## Arrancar

```sh
cp .env.example .env   # y rellena las contraseñas
scripts/instalar.sh    # instala y configura WordPress y WooCommerce para España
```

El script se puede repetir cuando quieras. Con `scripts/instalar.sh --desde-cero` borra la base de datos y WordPress, y
vuelve a empezar.

## Catálogo, IVA y envíos

- `contenido/productos.json` es el catálogo. `scripts/catalogo.sh` lo carga en WooCommerce (con las fotos de
  `assets/img/`) y se puede repetir sin duplicar nada: cada café es un producto variable con 8 variaciones
  (4 moliendas × 2 pesos).
- IVA: 10 % en el café y 21 % en los accesorios, con los precios ya con IVA incluido.
- Envíos (`scripts/envios.php`): península 4,90 € (gratis desde 35 €) y Baleares 7,90 €. No se envía a Canarias, Ceuta
  ni Melilla. Con solo accesorios el envío lleva el 21 % (5,39 € y 8,69 €) y con un carrito mixto el IVA del envío se
  reparte según lo que lleva. Las reglas están en `mu-plugins/tostela-tienda.php`.
- `scripts/probar-tienda.py` comprueba todo eso contra la API de la tienda, sin pagar nada.

## Cookies y analítica

- El aviso es de CookieYes, en español, con «Rechazar» tan visible como «Aceptar» (`scripts/wp-cookies.sh`). Las
  categorías y las cookies de la tienda están en `scripts/cookies.php`.
- Google Analytics (`G-7W4E0KE2BV`) y el píxel de Meta (`2587624965028846`) se cargan desde
  `mu-plugins/tostela-cookies.php`, con Consent Mode v2: todo denegado por defecto y, hasta que el visitante acepta,
  no se descarga nada de Google ni de Meta. Analítica gobierna Google Analytics y Publicidad gobierna el píxel de Meta.
- `scripts/probar-cookies.py` lo comprueba en un Chrome de verdad (necesita `pip install websocket-client`): primera
  visita, rechazar, aceptar, elegir solo analítica y retirar el consentimiento. Bloquea el envío real de datos para no
  ensuciar Analytics ni el píxel.

## Correo

- WooCommerce manda sus correos con WP Mail SMTP por Resend (`scripts/wp-correo.sh`). La clave y el remitente salen
  de `.env` (`RESEND_API_KEY` y `CORREO_REMITENTE`) y llegan a WordPress como constantes en `docker-compose.yml`, así
  que no se guardan en la base de datos.
- `scripts/probar-correo.sh [destinatario]` manda dos correos de prueba (por defecto a `delivered@resend.dev`) y
  consulta a Resend en qué estado están.

## Pagos y compras de prueba

- Los pagos son con tarjeta, con el plugin oficial de Stripe en modo de prueba (`scripts/wp-pagos.sh`). El script no toca
  las claves: la cuenta se conecta a mano desde WooCommerce > Ajustes > Pagos > Stripe, y conviene hacerlo entrando por
  `https://tostela.c2developers.com/wp-admin`, para que el aviso (webhook) de Stripe apunte al dominio público.
- Las compras de prueba se hacen en `http://localhost:8086`. En local la tienda se ve entera aunque en internet siga en
  «próximamente» (la regla está en `mu-plugins/tostela-tienda.php`) y WordPress usa esa dirección en los enlaces.

La tienda queda en <http://127.0.0.1:8086>. Para usar WP-CLI:

```sh
docker compose run --rm wpcli wp <orden>
```
