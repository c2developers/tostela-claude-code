# Tostela

Tienda online de ejemplo de café de especialidad, hecha con **WordPress y WooCommerce en Docker** para un tutorial de
YouTube: sin cuota de plataforma ni comisiones de Shopify. La marca y los productos son inventados, las fotos están
generadas con IA y la tienda funciona **en modo de prueba**.

- Web: <https://tostela.c2developers.com> (publicada con un túnel de Cloudflare) y <http://localhost:8086> en local.
- Todo en español de España: textos, euros e IVA (10 % el café, 21 % los accesorios).
- Pagos con tarjeta por Stripe (modo de prueba), correos por Resend, aviso de cookies con Consent Mode v2.

## Qué hay en el repositorio

| Carpeta o fichero | Para qué sirve |
|---|---|
| `docker-compose.yml` | MariaDB, WordPress (PHP 8.3), WP-CLI y el túnel de Cloudflare |
| `contenido/` | El catálogo (`productos.json`), los textos de las páginas y los prompts de las fotos |
| `assets/` | Las fotos y las fuentes (Fraunces e Inter) de partida |
| `tema/tostela/` | Tema hijo de Twenty Twenty-Five: todo el diseño está en `theme.json` |
| `mu-plugins/` | Reglas de la tienda: envíos e IVA, cookies y analítica, formulario de desistimiento |
| `scripts/` | Instalación, carga del catálogo, copias de seguridad y pruebas |
| `cloudflared/` | Reglas del túnel |
| `.env` | **Claves y contraseñas. No se sube a GitHub** (`.env.example` es la plantilla) |

## Montarla desde cero

**Necesitas:** Docker con Compose v2, `git`, `openssl` y `cloudflared` con la sesión iniciada
(`cloudflared tunnel login`), un dominio en Cloudflare, una cuenta de [Resend](https://resend.com) con el dominio de envío
verificado y una cuenta de [Stripe](https://stripe.com) (basta el modo de prueba).

**1. Clona el repositorio**

```sh
git clone https://github.com/c2developers/tostela-claude-code.git tostela
cd tostela
```

**2. Crea el túnel de Cloudflare** (una sola vez; cambia el dominio si usas otro)

```sh
cloudflared tunnel create tostela
cloudflared tunnel route dns tostela tostela.c2developers.com
```

**3. Crea el `.env`.** Genera las contraseñas directamente dentro del fichero, sin mostrarlas, y rellena tú la clave y
el remitente de Resend:

```sh
umask 077
cat > .env <<EOF
RESEND_API_KEY=pega-aquí-tu-clave-de-resend
CORREO_REMITENTE=Tostela <pedidos@tu-dominio-verificado.com>
DB_NAME=tostela
DB_USER=tostela
DB_PASSWORD=$(openssl rand -hex 24)
DB_ROOT_PASSWORD=$(openssl rand -hex 24)
WP_ADMIN_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=')
CLOUDFLARE_TUNNEL_TOKEN=$(cloudflared tunnel token tostela)
EOF
```

**4. Instala la tienda** (un par de minutos)

```sh
scripts/instalar.sh
```

Es repetible: lo que ya existe se actualiza. Hace, por este orden: instala WordPress y WooCommerce en español, con euros,
IVA y la dirección de la tienda; activa el tema; instala Stripe (solo tarjeta, modo de prueba), WP Mail SMTP con
Resend y CookieYes; crea las zonas de envío; carga el catálogo con sus fotos (7 cafés con 8 variaciones cada uno y 3
accesorios); crea las 8 páginas; y abre el túnel. `scripts/instalar.sh --desde-cero` borra la base de datos y WordPress y
empieza de nuevo (pide confirmación).

**5. Entra y mira**

- La tienda: <http://localhost:8086> o <https://tostela.c2developers.com>.
- El panel: `/wp-admin`, con el usuario `tostela-gestion` y la contraseña `WP_ADMIN_PASSWORD` de tu `.env`.
- Mientras no la publiques está en «próximamente» y con `noindex`: en internet ven un aviso; en `localhost` se ve entera.

**6. Conecta Stripe** (esto es manual: es tu cuenta). Entra por el **dominio público** (`/wp-admin`), ve a WooCommerce →
Ajustes → Pagos → Stripe y conéctalo en modo de prueba. Después registra el webhook de esta tienda:

```sh
scripts/stripe-webhook.sh
```

Sin el webhook, los pedidos pagados se quedan «pendientes». El script solo crea el endpoint de esta tienda y no toca los
de otras tiendas de la misma cuenta.

**7. Compra de prueba.** En <http://localhost:8086> con la tarjeta de prueba `4242 4242 4242 4242`, cualquier caducidad
y CVC. El pedido debe pasar a «procesando».

## Copias de seguridad

```sh
scripts/copia.sh                    # crea copias/AAAA-MM-DD_HHMMSS/
scripts/copia.sh --conservar 14     # además, se queda solo con las 14 últimas
scripts/restaurar.sh copias/AAAA-MM-DD_HHMMSS
```

Cada copia lleva **la base de datos** (pedidos, clientes, ajustes y claves de Stripe incluidos) y **las fotos** de la
mediateca, con sumas de comprobación. No lleva el `.env` (guárdalo aparte, en un gestor de contraseñas) ni los plugins
y el tema (los reinstala `scripts/instalar.sh`). `copias/` no se sube a GitHub, y se crea solo para tu usuario porque
contiene datos personales.

Para programarla cada noche (`crontab -e`): `0 3 * * * cd /ruta/a/tostela && scripts/copia.sh --conservar 14`

**Recuperar en otra máquina:** sigue los pasos 1 a 4 de arriba (con el mismo `.env`), copia la carpeta `copias/` y
ejecuta `scripts/restaurar.sh copias/…`. La restauración **sustituye** la base de datos y las fotos actuales, y pide que
escribas «sí».

## Pruebas

Comprueban la tienda por dentro y por fuera. Las de navegador necesitan Google Chrome y `pip install websocket-client`.

| Orden | Qué comprueba |
|---|---|
| `scripts/probar-tienda.py` | Envíos e IVA: península 4,90 € (gratis desde 35 €), Baleares 7,90 €, sin envío a Canarias, Ceuta y Melilla |
| `scripts/probar-cookies.py` | Ni Google ni Meta guardan nada antes de aceptar o al rechazar; al aceptar, se activan los dos |
| `scripts/probar-paginas.py` | Las 8 páginas, el pie, el pago y el formulario de desistimiento (manda 4 correos a `delivered@resend.dev`) |
| `scripts/probar-correo.sh` | Correos de prueba por Resend |

## Cómo está hecha

- **Catálogo:** `contenido/productos.json` → `scripts/catalogo.sh` (se puede repetir sin duplicar). Cada café es un
  producto variable con molienda × peso; los precios llevan el IVA incluido.
- **Envíos:** `scripts/envios.php` y `mu-plugins/tostela-tienda.php`. Con solo accesorios el envío lleva el 21 %
  (5,39 € y 8,69 €); con un carrito mixto, el IVA del envío se reparte según lo que lleva.
- **Pagos:** solo tarjeta. El plugin usa su propia lista de métodos y no la del panel de Stripe (`scripts/pagos.php`).
  Si Link está activo en tu cuenta de Stripe, el formulario de tarjeta puede ofrecerlo: eso solo se apaga en Stripe
  (Ajustes → Métodos de pago → Link). Stripe.js solo se carga en el carrito y al pagar.
- **Cookies:** CookieYes en español, con «Rechazar» tan visible como «Aceptar». Google Analytics (`G-7W4E0KE2BV`) y el
  píxel de Meta (`2587624965028846`) se cargan desde `mu-plugins/tostela-cookies.php` con Consent Mode v2, y solo si el
  visitante acepta su categoría. La atribución de pedidos de WooCommerce está desactivada porque guardaba cookies antes
  del consentimiento.
- **Correo:** WP Mail SMTP con Resend; la clave y el remitente salen del `.env` como constantes, no de la base de datos.
- **Desistimiento:** el formulario de `/desistir/` (`mu-plugins/tostela-desistimiento.php`) avisa a la tienda, manda un
  acuse de recibo al cliente y deja constancia en WooCommerce → Desistimientos y en el pedido.
- **Pago:** hay que aceptar las condiciones (con enlaces a condiciones, privacidad, desistimiento y devoluciones) y el
  botón dice «Realizar pedido con obligación de pago».

## Antes de vender de verdad

Esto es un ejemplo de tutorial. Antes de cobrar a nadie:

1. **Textos legales.** Las plantillas siguen con lo que va entre corchetes (`[Razón social]`, `[NIF]`, `[dirección]`…) y
   con el aviso de plantilla. Rellénalas y que las revise un abogado.
2. **Stripe en modo real.** Cambia a las claves reales y registra el webhook real; `scripts/pagos.php` deja
   `testmode` en «yes» y `scripts/stripe-webhook.sh` solo registra el de prueba.
3. **Publicar.** `scripts/wp-configurar.sh` deja la tienda en «próximamente» y con `blog_public` a 0 cada vez que se
   ejecuta: cámbialo allí o se revertirá al repetir la instalación.
4. **Datos de la tienda.** La dirección (`scripts/wp-configurar.sh`) y la empresa de ejemplo son inventadas. Los
   identificadores de Analytics y de Meta (arriba de `mu-plugins/tostela-cookies.php`), los de tu cuenta.
5. **Correo del administrador.** Comprueba que recibes los avisos de pedido y de desistimiento: Resend no entrega a las
   direcciones que tiene en su lista de supresiones.
6. **Otro dominio.** El dominio `tostela.c2developers.com` aparece en `docker-compose.yml`, `cloudflared/config.yml`,
   `scripts/wp-configurar.sh` y `scripts/cookies.php` (busca el texto con `grep -rn c2developers.com`): cámbialo en todos.

## Reglas de este repositorio

- Las claves y contraseñas viven solo en `.env`, que está en `.gitignore`. No subas nunca copias de seguridad
  (`copias/`), capturas ni nada con datos de clientes.
- Commits pequeños y con el mensaje en español.
