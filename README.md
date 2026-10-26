# Tostela

Tienda online de ejemplo de café de especialidad, hecha con WordPress y WooCommerce en Docker para un tutorial de
YouTube. La marca y los productos son inventados, las fotos están generadas con IA y la tienda funciona en modo de prueba.

## Arrancar

```sh
cp .env.example .env   # y rellena las contraseñas
docker compose up -d
```

La tienda queda en <http://127.0.0.1:8086>. Para usar WP-CLI:

```sh
docker compose run --rm wpcli wp <orden>
```
