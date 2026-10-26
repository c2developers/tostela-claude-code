#!/usr/bin/env python3
"""Prueba las páginas de Tostela, los enlaces del pie y del pago, y el formulario de desistimiento.

Uso: scripts/probar-paginas.py [url]      (por defecto, http://localhost:8086)
Necesita Google Chrome y `websocket-client` (para ver el pago, que lo dibuja JavaScript) y Docker (para mirar
la base de datos y Resend). Manda 4 correos de prueba a delivered@resend.dev y no toca ningún pedido real.
"""
import html
import html.parser
import os
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from datetime import date, timedelta

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from navegador import Navegador  # noqa: E402

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://localhost:8086").rstrip("/")
RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BUZON_PRUEBAS = "delivered@resend.dev"

PAGINAS = {
    "nuestra-historia": "Nuestra historia",
    "contacto": "Contacto",
    "envios-y-devoluciones": "Envíos y devoluciones",
    "desistir": "Desistir del contrato",
    "aviso-legal": "Aviso legal",
    "condiciones-de-venta": "Condiciones de venta",
    "politica-de-privacidad": "Política de privacidad",
    "politica-de-cookies": "Política de cookies",
}
fallos = 0


def comprobar(nombre, correcto, detalle=""):
    global fallos
    fallos += 0 if correcto else 1
    print(f"  {'✓' if correcto else '✗'} {nombre}" + (f"\n      {detalle}" if detalle and not correcto else ""))
    return correcto


class SinRedirigir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def pedir(ruta, datos=None):
    """(código, cabeceras, cuerpo, destino de la redirección). No sigue las redirecciones."""
    peticion = urllib.request.Request(BASE + ruta, data=urllib.parse.urlencode(datos).encode() if datos is not None else None)
    abrir = urllib.request.build_opener(SinRedirigir).open
    try:
        r = abrir(peticion, timeout=30)
    except urllib.error.HTTPError as e:
        r = e
    return r.status if hasattr(r, "status") else r.code, r.headers, r.read().decode("utf-8", "replace"), r.headers.get("Location")


def wp(php):
    salida = subprocess.run(
        ["docker", "compose", "run", "--rm", "wpcli", "wp", "eval", php], cwd=RAIZ, capture_output=True, text=True
    ).stdout
    return "\n".join(l for l in salida.splitlines() if "Container" not in l and "level=warning" not in l).strip()


def texto(fragmento):
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", fragmento))).strip()


class EnlacesDelPie(html.parser.HTMLParser):
    def __init__(self):
        super().__init__()
        self.dentro = 0
        self.enlaces = []

    def handle_starttag(self, etiqueta, atributos):
        if etiqueta == "footer":
            self.dentro += 1
        elif etiqueta == "a" and self.dentro:
            self.enlaces.append(dict(atributos).get("href", ""))

    def handle_endtag(self, etiqueta):
        if etiqueta == "footer":
            self.dentro -= 1


def paginas():
    print("\n— Las 8 páginas")
    for slug, titulo in PAGINAS.items():
        codigo, _, cuerpo, _ = pedir(f"/{slug}/")
        primera = re.search(r"<p[^>]*>(.*?)</p>", open(f"{RAIZ}/contenido/paginas/{slug}.html").read().split("-->", 1)[1], re.S)
        # Una frase del texto original (la primera que no sea el aviso de plantilla), para comprobar que es el suyo.
        frases = [texto(p) for p in re.findall(r"<p[^>]*>(.*?)</p>", open(f"{RAIZ}/contenido/paginas/{slug}.html").read(), re.S)]
        frase = next((f for f in frases if f and not f.startswith("Plantilla de ejemplo")), "")[:60]
        comprobar(
            f"/{slug}/ carga con «{titulo}» y su texto",
            codigo == 200 and titulo in cuerpo and frase in texto(cuerpo) and "[tostela_desistir]" not in cuerpo,
            f"código {codigo}; frase buscada: {frase!r}",
        )


def pie_y_menu():
    print("\n— Enlaces del pie y del menú")
    _, _, cuerpo, _ = pedir("/")
    parser = EnlacesDelPie()
    parser.feed(cuerpo)
    esperados = ["/aviso-legal/", "/condiciones-de-venta/", "/politica-de-privacidad/", "/politica-de-cookies/", "/envios-y-devoluciones/", "/desistir/", "/contacto/", "/nuestra-historia/"]
    comprobar("El pie enlaza a las 4 legales y a las 4 de ayuda", all(e in parser.enlaces for e in esperados), f"faltan {[e for e in esperados if e not in parser.enlaces]}")
    rotos = []
    for enlace in sorted({e for e in parser.enlaces if e.startswith("/")}):
        codigo = pedir(enlace)[0]
        if codigo != 200:
            rotos.append((enlace, codigo))
    comprobar("Todos los enlaces internos del pie funcionan (200)", not rotos, str(rotos))
    menu = re.findall(r'href="(/[^"#?]*)"[^>]*>\s*(?:<[^>]+>\s*)*(Cafés|Accesorios|Nuestra historia|Contacto)<', cuerpo)
    comprobar("Los enlaces del menú funcionan", bool(menu) and all(pedir(r)[0] == 200 for r, _ in menu), str(menu))


def pago():
    print("\n— La página de pago")
    _, _, cuerpo, _ = pedir("/wp-json/wc/store/v1/products?sku=ACC-FIL")
    id_producto = re.search(r'"id":(\d+)', cuerpo).group(1)
    n = Navegador(BASE)
    try:
        n.ir(f"/?add-to-cart={id_producto}")
        n.ir("/finalizar-compra/")
        n.esperar(6)
        terminos = n.js(
            """(function(){var t=document.querySelector('.wc-block-checkout__terms');if(!t)return null;
              return JSON.stringify({texto:t.innerText.replace(/\\s+/g,' ').trim(),casilla:!!t.querySelector('input[type=checkbox]'),
                enlaces:Array.from(t.querySelectorAll('a')).map(function(a){return a.getAttribute('href')})})})()"""
        )
        boton = n.js("(function(){var b=document.querySelector('.wc-block-components-checkout-place-order-button');return b?b.innerText.trim():null})()")
    finally:
        n.cerrar()
    import json

    t = json.loads(terminos) if terminos else {}
    print(f"      Texto: {t.get('texto')!r}\n      Botón: {boton!r}")
    enlaces = " ".join(t.get("enlaces", []))
    comprobar("Hay que marcar una casilla para aceptar las condiciones", bool(t.get("casilla")))
    comprobar("El pago enlaza a las condiciones de venta", "condiciones-de-venta" in enlaces, enlaces)
    comprobar("El pago enlaza a la política de privacidad", "politica-de-privacidad" in enlaces, enlaces)
    comprobar("El pago recuerda el derecho de desistimiento y enlaza a «Desistir» y a «Envíos y devoluciones»", "/desistir/" in enlaces and "/envios-y-devoluciones/" in enlaces, enlaces)
    comprobar("El botón dice que el pedido obliga a pagar", bool(boton) and "obligación de pago" in boton.lower(), str(boton))  # El botón sale en mayúsculas por el CSS.


def desistimiento():
    print("\n— El formulario de desistimiento")
    wp(f"update_option('tostela_desistir_destino', '{BUZON_PRUEBAS}');")  # el aviso de la tienda también al buzón de pruebas
    pedido_temporal = None
    try:
        codigo, cab, cuerpo, _ = pedir("/desistir/")
        nonce = re.search(r'name="tostela_nonce" value="([^"]+)"', cuerpo)
        campos = all(f'name="{c}"' in cuerpo for c in ("nombre", "correo", "pedido", "fecha", "articulos"))
        comprobar("El formulario aparece con todos sus campos", codigo == 200 and bool(nonce) and campos)
        comprobar("La página del formulario no se guarda en caché", "no-store" in cab.get("Cache-Control", "") or "no-cache" in cab.get("Cache-Control", ""), cab.get("Cache-Control", ""))
        if not nonce:
            return
        base = {"tostela_desistir": "1", "tostela_nonce": nonce.group(1), "nombre": "Ana Prueba", "correo": BUZON_PRUEBAS, "pedido": "96",
                "fecha": (date.today() - timedelta(days=3)).isoformat(), "articulos": "", "web": ""}

        c, _, cuerpo, _ = pedir("/desistir/", {**base, "nombre": "", "correo": "no-es-un-correo", "pedido": "abc", "fecha": (date.today() + timedelta(days=2)).isoformat()})
        errores = texto(cuerpo)
        comprobar(
            "Con datos mal puestos no envía nada y explica los 4 errores",
            c == 200 and all(f in errores for f in ("nombre y apellidos", "correo electrónico válido", "número de pedido", "no puede ser futura")),
            errores[errores.find("Revisa"):][:300] if "Revisa" in errores else errores[:200],
        )
        c, _, cuerpo, _ = pedir("/desistir/", {**base, "nombre": "Conservame", "correo": ""})
        comprobar("Al fallar, conserva lo que habías escrito", c == 200 and 'value="Conservame"' in cuerpo)
        c, _, cuerpo, _ = pedir("/desistir/", {**base, "tostela_nonce": "caducado"})
        comprobar("Con un formulario caducado o falsificado lo rechaza", c == 200 and "ha caducado" in cuerpo)

        antes = wp("echo wp_count_posts('tostela_desistir')->private;")
        c, _, _, destino = pedir("/desistir/", {**base, "web": "soy un robot"})
        despues = wp("echo wp_count_posts('tostela_desistir')->private;")
        comprobar("La casilla trampa de los robots no deja constancia ni manda correos", c == 303 and "AAAAA" in (destino or "") and antes == despues, f"{c} {destino} {antes}->{despues}")

        # Un pedido temporal con el correo de pruebas: así se comprueba que queda una nota en el pedido.
        pedido_temporal = wp(
            f"$o = wc_create_order(); $o->set_billing_email('{BUZON_PRUEBAS}'); $o->set_billing_first_name('Ana'); $o->save(); echo $o->get_id();"
        )
        c, _, _, destino = pedir("/desistir/", {**base, "pedido": f"#{pedido_temporal}"})
        ref = re.search(r"referencia=(DES-\d{8}-[A-Z0-9]{5})", destino or "")
        comprobar("Un envío correcto redirige a la confirmación con una referencia", c == 303 and bool(ref), f"{c} {destino}")
        if not ref:
            return
        ref = ref.group(1)
        c, _, cuerpo, _ = pedir(f"/desistir/?referencia={ref}")
        comprobar("La confirmación enseña la referencia y avisa del acuse de recibo", c == 200 and ref in cuerpo and "acuse de recibo" in cuerpo)
        c, _, cuerpo, _ = pedir("/desistir/?referencia=DES-00000000-XXXXX")
        comprobar("Una referencia inventada no enseña ninguna confirmación... salvo el formato, sin datos de nadie", "Revisa" not in cuerpo and "Ana Prueba" not in cuerpo)

        registro = wp(
            f"$p = get_posts(array('post_type'=>'tostela_desistir','post_status'=>'private','s'=>'{ref}','numberposts'=>1)); "
            "echo $p ? $p[0]->post_title . '|' . str_replace(\"\\n\", ' / ', $p[0]->post_content) : 'NO HAY REGISTRO';"
        )
        comprobar("Queda constancia en el panel (WooCommerce > Desistimientos)", ref in registro and "En plazo (14 días): sí" in registro and "El correo coincide con el del pedido: sí" in registro, registro[:300])
        nota = wp(f"foreach (wc_get_order_notes(array('order_id'=>{pedido_temporal})) as $n) echo $n->content, ' ## ';")
        comprobar("Queda una nota en el pedido", ref in nota, nota[:200])

        # Los dos correos, mirados en Resend (el aviso de la tienda y el acuse de recibo del cliente).
        resultado = wp(
            "$cab = array('headers' => array('Authorization' => 'Bearer ' . WPMS_RESEND_API_KEY), 'timeout' => 20); "
            "for ($i = 0; $i < 20; $i++) { sleep(3); "
            "$d = json_decode(wp_remote_retrieve_body(wp_remote_get('https://api.resend.com/emails?limit=12', $cab)), true); "
            f"$m = array_values(array_filter($d['data'] ?? array(), function ($c) {{ return strpos($c['subject'] ?? '', '{ref}') !== false; }})); "
            "if (count($m) >= 2) break; } "
            "foreach ($m as $c) echo $c['subject'], ' => ', $c['last_event'] ?? '?', \"\\n\";"
        )
        print("      Correos vistos en Resend:\n        " + (resultado.replace("\n", "\n        ") or "ninguno"))
        comprobar("Resend recibe el aviso para la tienda", f"Desistimiento del pedido #{pedido_temporal} ({ref})" in resultado)
        comprobar("Resend recibe el acuse de recibo para el cliente", f"Hemos recibido tu desistimiento ({ref})" in resultado)

        # El límite de envíos por hora y visitante (se prueba directamente, sin mandar más correos).
        limite = wp(
            "$_SERVER['REMOTE_ADDR'] = '203.0.113.9'; set_transient(tostela_desistir_clave_visitante(), 5, 60); "
            f"$r = tostela_desistir_gestionar(array('tostela_nonce' => wp_create_nonce('tostela_desistir'), 'nombre' => 'A', 'correo' => '{BUZON_PRUEBAS}', 'pedido' => '1', 'fecha' => '{date.today().isoformat()}')); "
            "delete_transient(tostela_desistir_clave_visitante()); echo isset($r['errores']) ? $r['errores'][0] : 'NO LIMITÓ';"
        )
        comprobar("Más de 5 envíos en una hora desde el mismo sitio se bloquean", "demasiadas solicitudes" in limite, limite)
    finally:
        wp("delete_option('tostela_desistir_destino');")
        if pedido_temporal:
            wp(f"wc_get_order({pedido_temporal})->delete(true);")
        wp(
            "foreach (get_posts(array('post_type'=>'tostela_desistir','post_status'=>'any','numberposts'=>-1,'s'=>'Ana Prueba')) as $p) wp_delete_post($p->ID, true);"
        )


print(f"Probando {BASE}")
paginas()
pie_y_menu()
pago()
desistimiento()
print(f"\n{'Todo correcto.' if not fallos else f'{fallos} comprobación(es) con fallo.'}")
sys.exit(1 if fallos else 0)
