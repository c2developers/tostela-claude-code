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

La tienda queda en <http://127.0.0.1:8086>. Para usar WP-CLI:

```sh
docker compose run --rm wpcli wp <orden>
```
