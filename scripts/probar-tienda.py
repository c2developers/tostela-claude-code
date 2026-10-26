#!/usr/bin/env python3
"""Prueba los envíos y el IVA de Tostela contra la API de la tienda (Store API), sin pagar nada.

Uso: scripts/probar-tienda.py [url]      (por defecto, http://127.0.0.1:8086)

Cada caso añade productos al carrito, pone una dirección y compara el envío que devuelve la tienda con
el que debería salir según las reglas: 4,90 € en la península (gratis desde 35 €), 7,90 € en Baleares,
con el IVA del café (10 %) o el de los accesorios (21 %) repartido según lo que lleva el carrito.
"""
import json
import sys
import urllib.error
import urllib.request

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8086"
API = BASE + "/wp-json/wc/store/v1"

# Precios con IVA incluido y su tipo (10 % café, 21 % accesorios), tal como están en contenido/productos.json.
CAFE_250 = {"CAF-BRA": 11.90, "CAF-COL": 12.90, "CAF-ETI": 14.50, "CAF-MAN": 11.50}
FILTROS = ("ACC-FIL", 6.50)
GOTERO = ("ACC-GOT", 24.00)

PENINSULA = {"state": "M", "postcode": "28012", "city": "Madrid"}
BALEARES = {"state": "PM", "postcode": "07001", "city": "Palma"}


class Carrito:
    def __init__(self):
        self.token = None
        self.nonce = None
        self.llamar("GET", "/cart")

    def llamar(self, metodo, ruta, cuerpo=None):
        cabeceras = {"Content-Type": "application/json"}
        if self.token:
            cabeceras["Cart-Token"] = self.token
        if self.nonce:
            cabeceras["Nonce"] = self.nonce
        datos = json.dumps(cuerpo).encode() if cuerpo is not None else None
        peticion = urllib.request.Request(API + ruta, data=datos, headers=cabeceras, method=metodo)
        try:
            respuesta = urllib.request.urlopen(peticion)
        except urllib.error.HTTPError as error:
            respuesta = error
        self.token = respuesta.headers.get("Cart-Token", self.token)
        self.nonce = respuesta.headers.get("Nonce", self.nonce)
        return respuesta.status, json.loads(respuesta.read() or b"null")

    def anadir(self, sku, cantidad=1):
        estado, productos = self.llamar("GET", f"/products?sku={sku.split('-')[0]}-{sku.split('-')[1]}")
        producto = productos[0]
        cuerpo = {"id": producto["id"], "quantity": cantidad}
        if producto["type"] == "variable":
            # Siempre en grano y 250 g: el peso y la molienda no cambian el IVA ni el envío.
            variacion = next(
                v for v in producto["variations"]
                if {a["value"] for a in v["attributes"]} == {"en-grano", "250-g"}
            )
            cuerpo["id"] = variacion["id"]
            cuerpo["variation"] = [{"attribute": a["name"], "value": a["value"]} for a in variacion["attributes"]]
        estado, respuesta = self.llamar("POST", "/cart/add-item", cuerpo)
        assert estado in (200, 201), f"No se pudo añadir {sku}: {respuesta}"

    def direccion(self, zona):
        direccion = {
            "first_name": "Prueba", "last_name": "Tostela", "address_1": "Calle de Prueba 1",
            "country": "ES", **zona,
        }
        return self.llamar(
            "POST", "/cart/update-customer",
            {"shipping_address": direccion, "billing_address": {**direccion, "email": "prueba@example.com", "phone": "600000000"}},
        )


def euros(centimos):
    return f"{int(centimos) / 100:.2f}".replace(".", ",") + " €"


def lineas_carrito(lineas):
    """Devuelve [(precio con IVA, tipo de IVA)] de las líneas, para calcular lo esperado."""
    resultado = []
    for sku, cantidad in lineas:
        if sku in CAFE_250:
            resultado.append((CAFE_250[sku] * cantidad, 0.10))
        else:
            precio = dict([FILTROS, GOTERO])[sku]
            resultado.append((precio * cantidad, 0.21))
    return resultado


def envio_esperado(lineas, bruto_cafe):
    """Envío con IVA: coste sin IVA (bruto de café / 1,10) y el IVA repartido según el valor de cada tipo."""
    neto = bruto_cafe / 1.10
    valores = {}
    for precio, tipo in lineas_carrito(lineas):
        valores[tipo] = valores.get(tipo, 0) + precio / (1 + tipo)
    total = sum(valores.values())
    iva = sum(neto * (valor / total) * tipo for tipo, valor in valores.items())
    return round(neto + iva, 2)


fallos = 0


def comprobar(nombre, zona, lineas, bruto_cafe, esperado=None, gratis=False):
    """Un caso: esperado = importe del envío con IVA, o gratis=True si solo debe haber envío gratis."""
    global fallos
    carrito = Carrito()
    for sku, cantidad in lineas:
        carrito.anadir(sku, cantidad)
    estado, cesta = carrito.direccion(zona)
    assert estado == 200, f"{nombre}: la tienda rechazó la dirección: {cesta}"
    tarifas = [t for paquete in cesta["shipping_rates"] for t in paquete["shipping_rates"]]
    nombres = [t["name"] for t in tarifas]
    # La tienda da cada tarifa sin IVA y el IVA aparte: el importe que paga el cliente es la suma.
    con_iva = [int(t["price"]) + int(t["taxes"] or 0) for t in tarifas]
    if gratis:
        correcto = len(tarifas) == 1 and con_iva[0] == 0
        detalle = f"tarifas: {nombres}"
    else:
        esperado = esperado if esperado is not None else envio_esperado(lineas, bruto_cafe)
        correcto = len(tarifas) == 1 and abs(con_iva[0] / 100 - esperado) < 0.011
        detalle = f"tarifa: {nombres}, importe {euros(con_iva[0]) if tarifas else '—'}, esperado {euros(round(esperado * 100))}"
    fallos += 0 if correcto else 1
    print(f"{'✓' if correcto else '✗'} {nombre}\n    {detalle}")


def comprobar_rechazo(nombre, provincia, cp):
    global fallos
    carrito = Carrito()
    carrito.anadir("CAF-BRA")
    estado, cuerpo = carrito.direccion({"state": provincia, "postcode": cp, "city": "Prueba"})
    correcto = estado >= 400
    fallos += 0 if correcto else 1
    print(f"{'✓' if correcto else '✗'} {nombre}\n    la tienda responde {estado}" + ("" if correcto else " (debería rechazarlo)"))


print(f"Probando {BASE}\n")
brasil, colombia, etiopia, manana = "CAF-BRA", "CAF-COL", "CAF-ETI", "CAF-MAN"

print("— Península —")
comprobar("1 café (11,90 €): 4,90 €", PENINSULA, [(brasil, 1)], 4.90, esperado=4.90)
comprobar("Solo accesorios (filtros): 5,39 €", PENINSULA, [FILTROS[:1] + (1,)], 4.90, esperado=5.39)
comprobar("Café + filtros (18,40 €): IVA del envío repartido", PENINSULA, [(brasil, 1), ("ACC-FIL", 1)], 4.90)
comprobar("3 × Mezcla Mañana (34,50 €, justo por debajo): se paga el envío", PENINSULA, [(manana, 3)], 4.90, esperado=4.90)
comprobar("2 × Etiopía + filtros (35,50 €): envío gratis", PENINSULA, [(etiopia, 2), ("ACC-FIL", 1)], 4.90, gratis=True)
comprobar("3 × Colombia (38,70 €): envío gratis", PENINSULA, [(colombia, 3)], 4.90, gratis=True)

print("\n— Baleares —")
comprobar("1 café: 7,90 €", BALEARES, [(brasil, 1)], 7.90, esperado=7.90)
comprobar("Solo accesorios (gotero): 8,69 €", BALEARES, [("ACC-GOT", 1)], 7.90, esperado=8.69)
comprobar("Café + gotero (35,90 €): sin envío gratis, IVA repartido", BALEARES, [(brasil, 1), ("ACC-GOT", 1)], 7.90)
comprobar("4 × Colombia (51,60 €): en Baleares nunca es gratis", BALEARES, [(colombia, 4)], 7.90, esperado=7.90)

print("\n— Sin envío —")
comprobar_rechazo("Las Palmas (Canarias)", "GC", "35001")
comprobar_rechazo("Santa Cruz de Tenerife (Canarias)", "TF", "38001")
comprobar_rechazo("Ceuta", "CE", "51001")
comprobar_rechazo("Melilla", "ML", "52001")

print(f"\n{'Todo correcto.' if not fallos else f'{fallos} caso(s) con fallo.'}")
sys.exit(1 if fallos else 0)
