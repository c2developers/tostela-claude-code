#!/usr/bin/env python3
"""Prueba el aviso de cookies y el Consent Mode v2 de Tostela en un Chrome de verdad (sin ventana).

Uso: scripts/probar-cookies.py [url]      (por defecto, http://localhost:8086)
Necesita Google Chrome y el módulo `websocket-client` (pip install websocket-client).

Cada caso usa un perfil de navegador limpio y mira qué peticiones salen a Google y a Meta. El envío real de datos
(las llamadas de «collect» y el /tr de Meta) se bloquea para no ensuciar Analytics ni el píxel con visitas de
prueba, pero el intento se sigue viendo y se comprueba.
"""
import json
import re
import sys

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://localhost:8086").rstrip("/")
GA_ID = "G-7W4E0KE2BV"
PIXEL_ID = "2587624965028846"

sys.path.insert(0, __import__("os").path.dirname(__file__))
from navegador import Navegador as NavegadorBase, SEGUIMIENTO  # noqa: E402


class Navegador(NavegadorBase):
    def __init__(self):
        super().__init__(BASE)


fallos = 0


def comprobar(nombre, correcto, detalle=""):
    global fallos
    fallos += 0 if correcto else 1
    print(f"  {'✓' if correcto else '✗'} {nombre}" + (f"\n      {detalle}" if detalle and not correcto else ""))
    return correcto


def consentimiento(navegador):
    """La cookie cookieyes-consent como diccionario (necessary:yes, analytics:no…)."""
    valor = navegador.cookies().get("cookieyes-consent", "")
    return dict(par.split(":", 1) for par in valor.replace("%3A", ":").replace("%2C", ",").split(",") if ":" in par)


def estados_consentimiento(navegador, tipo):
    """Los estados que Consent Mode recibió en el dataLayer: ('default' o 'update')."""
    return [e[2] for e in navegador.data_layer() if len(e) >= 3 and e[0] == "consent" and e[1] == tipo]


def peticiones_google_meta(navegador):
    urls = navegador.seguimiento()
    return {
        "gtag.js": [u for u in urls if "googletagmanager.com/gtag/js" in u and GA_ID in u],
        "collect": [u for u in urls if "collect" in u and ("google-analytics.com" in u or "analytics.google.com" in u)],
        "fbevents": [u for u in urls if "connect.facebook.net" in u and "fbevents" in u],
        "tr": [u for u in urls if "facebook.com/tr" in u],
        "todas": urls,
    }


def caso(titulo, funcion):
    print(f"\n— {titulo}")
    navegador = Navegador()
    try:
        funcion(navegador)
    finally:
        navegador.cerrar()


def primera_visita(n):
    n.ir("/")
    n.js("window.scrollTo(0, document.body.scrollHeight)")
    n.esperar(6)
    p = peticiones_google_meta(n)
    comprobar("Sin tocar nada no sale ninguna petición a Google ni a Meta", not p["todas"], str(p["todas"]))
    comprobar("No hay cookies de Google ni de Meta", not any(c in n.cookies() for c in ("_ga", "_fbp")) and not any(c.startswith("_ga_") for c in n.cookies()))
    comprobar("Desplazarse y esperar no cuenta como aceptar", consentimiento(n).get("action") != "yes", str(consentimiento(n)))
    defecto = estados_consentimiento(n, "default")
    comprobar(
        "Consent Mode v2: todo denegado por defecto",
        len(defecto) == 1 and all(defecto[0].get(k) == "denied" for k in ("ad_storage", "ad_user_data", "ad_personalization", "analytics_storage")),
        str(defecto),
    )
    estilos = n.js(
        """(function(){
          function d(t){var e=document.querySelector('[data-cky-tag="'+t+'"]');if(!e)return null;var r=e.getBoundingClientRect(),c=getComputedStyle(e);
            return {texto:e.textContent.trim(),visible:r.width>0&&r.height>0&&c.visibility!=='hidden'&&c.display!=='none',ancho:Math.round(r.width),alto:Math.round(r.height),
              fondo:c.backgroundColor,color:c.color,borde:c.borderTopColor+' '+c.borderTopWidth,fuente:c.fontSize+' '+c.fontWeight,opacidad:c.opacity};}
          return JSON.stringify({aceptar:d('accept-button'),rechazar:d('reject-button'),personalizar:d('settings-button')});})()"""
    )
    botones = json.loads(estilos or "{}")
    a, r = botones.get("aceptar"), botones.get("rechazar")
    comprobar("El aviso está visible, en español, con «Aceptar» y «Rechazar»", bool(a and r and a["visible"] and r["visible"] and a["texto"] == "Aceptar" and r["texto"] == "Rechazar"), str(botones))
    if a and r:
        igual = {k: a[k] == r[k] for k in ("ancho", "alto", "fondo", "color", "borde", "fuente", "opacidad")}
        comprobar("«Rechazar» es idéntico a «Aceptar» (tamaño, color, borde, letra)", all(igual.values()), f"{a} frente a {r}")
        print(f"      Aceptar: {a['ancho']}×{a['alto']} px, fondo {a['fondo']}  |  Rechazar: {r['ancho']}×{r['alto']} px, fondo {r['fondo']}")
    return botones


def rechazar(n):
    n.ir("/")
    n.clic('[data-cky-tag="reject-button"]')
    n.esperar(4)
    c = consentimiento(n)
    comprobar("Queda guardado: analítica y publicidad en «no»", c.get("action") == "yes" and c.get("analytics") == "no" and c.get("advertisement") == "no", str(c))
    comprobar("Tras rechazar no sale ninguna petición a Google ni a Meta", not n.seguimiento(), str(n.seguimiento()))
    actualizaciones = estados_consentimiento(n, "update")
    comprobar("Consent Mode se actualiza con todo denegado", bool(actualizaciones) and all(v == "denied" for v in actualizaciones[-1].values()), str(actualizaciones))
    n.peticiones.clear()
    n.ir("/tienda/")
    comprobar("Al volver a otra página, el aviso no reaparece y no sale nada a Google ni a Meta", not n.seguimiento() and consentimiento(n).get("action") == "yes", str(n.seguimiento()))


def aceptar(n):
    n.ir("/")
    n.clic('[data-cky-tag="accept-button"]')
    n.esperar(8)
    c = consentimiento(n)
    comprobar("Queda guardado: analítica y publicidad en «sí»", c.get("analytics") == "yes" and c.get("advertisement") == "yes", str(c))
    p = peticiones_google_meta(n)
    comprobar(f"Se carga Google Analytics ({GA_ID})", bool(p["gtag.js"]), str(p["todas"]))
    comprobar("Google Analytics intenta enviar la visita, con el estado del consentimiento", any("tid=" + GA_ID in u or GA_ID in u for u in p["collect"]), str(p["collect"]))
    comprobar("Se carga el píxel de Meta", bool(p["fbevents"]), str(p["todas"]))
    comprobar(f"El píxel de Meta intenta enviar PageView ({PIXEL_ID})", any(PIXEL_ID in u and "ev=PageView" in u for u in p["tr"]), str(p["tr"]))
    actualizaciones = estados_consentimiento(n, "update")
    comprobar("Consent Mode se actualiza con todo concedido", bool(actualizaciones) and all(v == "granted" for v in actualizaciones[-1].values()), str(actualizaciones))
    for u in p["collect"][:1]:
        gcs = [parte for parte in u.split("&") if parte.startswith(("gcs=", "gcd="))]
        print(f"      Parámetros de consentimiento en la visita de Analytics: {gcs}")
    n.peticiones.clear()
    n.ir("/tienda/")
    p = peticiones_google_meta(n)
    comprobar("En la siguiente visita se cargan solas (recuerda lo aceptado)", bool(p["gtag.js"]) and bool(p["fbevents"]), str(p["todas"]))


def solo_analitica(n):
    n.ir("/")
    n.clic('[data-cky-tag="settings-button"]')
    n.esperar(1.5)
    marcas = json.loads(
        n.js(
            """JSON.stringify(Array.prototype.map.call(document.querySelectorAll('[data-cky-tag="detail"] input[type=checkbox]'),
               function(i){return {id:i.id,marcada:i.checked,desactivada:i.disabled}}))"""
        )
        or "[]"
    )
    publicidad = next((m for m in marcas if "advertisement" in m["id"]), None)
    analitica = next((m for m in marcas if "analytics" in m["id"]), None)
    # Las necesarias salen como «Siempre activas», sin interruptor: solo se pueden elegir estas dos.
    comprobar("El centro de preferencias deja elegir Analítica y Publicidad, y nada más", bool(publicidad and analitica and len(marcas) == 2), str(marcas))
    if not (publicidad and analitica):
        return
    comprobar("Todas las opciones opcionales empiezan desmarcadas", not publicidad["marcada"] and not analitica["marcada"], str(marcas))
    n.clic("#" + analitica["id"])
    n.clic('[data-cky-tag="detail-save-button"]')
    n.esperar(8)
    c = consentimiento(n)
    comprobar("Se guarda: analítica «sí» y publicidad «no»", c.get("analytics") == "yes" and c.get("advertisement") == "no", str(c))
    p = peticiones_google_meta(n)
    comprobar("Se carga Google Analytics", bool(p["gtag.js"]), str(p["todas"]))
    comprobar("No se carga nada de Meta", not p["fbevents"] and not p["tr"], str(p["todas"]))
    actualizaciones = estados_consentimiento(n, "update")
    ultimo = actualizaciones[-1] if actualizaciones else {}
    comprobar(
        "Consent Mode: analytics_storage concedido y los permisos de anuncios denegados",
        ultimo.get("analytics_storage") == "granted" and all(ultimo.get(k) == "denied" for k in ("ad_storage", "ad_user_data", "ad_personalization")),
        str(ultimo),
    )


def retirar_consentimiento(n):
    n.ir("/")
    n.clic('[data-cky-tag="accept-button"]')
    n.esperar(6)
    antes = set(n.cookies())
    n.clic('[data-cky-tag="revisit-consent"]')
    n.esperar(1.5)
    n.clic('[data-cky-tag="detail-reject-button"]')
    n.esperar(4)
    c = consentimiento(n)
    comprobar("Desde el botón de cookies se puede cambiar de idea: todo «no»", c.get("analytics") == "no" and c.get("advertisement") == "no", str(c))
    despues = set(n.cookies())
    restos = sorted(x for x in despues if x in ("_ga", "_fbp") or x.startswith("_ga_"))
    comprobar("Se borran las cookies de Google y de Meta", not restos, f"quedan {restos} (había {sorted(x for x in antes if x in ('_ga', '_fbp') or x.startswith('_ga_'))})")
    actualizaciones = estados_consentimiento(n, "update")
    comprobar("Consent Mode vuelve a denegado", bool(actualizaciones) and all(v == "denied" for v in actualizaciones[-1].values()), str(actualizaciones[-1:] if actualizaciones else ""))


PAGINAS = ("/", "/tienda/", "/producto/etiopia-yirgacheffe/", "/categoria-producto/accesorios/", "/carrito/")
NECESARIAS = re.compile(r"^(cookieyes.*|woocommerce_.*|wp_woocommerce_session_.*|wordpress_.*|wp-settings-.*)$")
# Stripe (prevención del fraude) solo puede guardar sus cookies en el carrito y al pagar, nunca antes.
NECESARIAS_CON_STRIPE = re.compile(NECESARIAS.pattern[:-2] + r"|__stripe_mid|__stripe_sid|m)$")
DE_GOOGLE_O_META = re.compile(r"^(_ga|_ga_.*|_gid|_gat.*|_gcl_.*|_gac_.*|_fbp|_fbc|fr|datr|sb)$")
DOMINIOS_AJENOS = re.compile(r"google|facebook|doubleclick|fb\.com|analytics")


def auditar(n, con_stripe=False):
    """Todas las cookies del navegador (de cualquier dominio) y el almacenamiento local, sin sus valores."""
    jar = [(c["name"], c["domain"]) for c in n.enviar("Network.getAllCookies")["cookies"]]
    permitidas = NECESARIAS_CON_STRIPE if con_stripe else NECESARIAS
    almacenamiento = json.loads(n.js("JSON.stringify({local:Object.keys(localStorage),sesion:Object.keys(sessionStorage)})") or "{}")
    return {
        "cookies": sorted(nombre for nombre, _ in jar),
        "de_google_o_meta": sorted(nombre for nombre, dominio in jar if DE_GOOGLE_O_META.match(nombre) or DOMINIOS_AJENOS.search(dominio)),
        "no_necesarias": sorted(nombre for nombre, _ in jar if not permitidas.match(nombre)),
        "almacenamiento": almacenamiento["local"] + almacenamiento["sesion"],
        "almacenamiento_ajeno": [k for k in almacenamiento["local"] + almacenamiento["sesion"] if re.search(r"_ga|_gcl|_fb|fbevents|google|facebook", k, re.I)],
    }


def recorrer(n, rutas):
    for ruta in rutas:
        n.ir(ruta)
        n.js("window.scrollTo(0, document.body.scrollHeight)")
        n.esperar(1)


def sin_nada_de_google_ni_meta(n, momento, con_stripe=False):
    a = auditar(n, con_stripe)
    print(f"      Cookies {momento}: {', '.join(a['cookies']) or 'ninguna'}  |  almacenamiento: {', '.join(a['almacenamiento']) or 'vacío'}")
    comprobar(f"{momento}: ni Google ni Meta han guardado ninguna cookie", not a["de_google_o_meta"], str(a["de_google_o_meta"]))
    comprobar(
        f"{momento}: solo hay cookies necesarias de la tienda" + (" (y las de Stripe, que se permiten en el carrito)" if con_stripe else " (ni siquiera las de Stripe)"),
        not a["no_necesarias"], str(a["no_necesarias"]),
    )
    comprobar(f"{momento}: nada de Google ni Meta en el almacenamiento local", not a["almacenamiento_ajeno"], str(a["almacenamiento_ajeno"]))
    comprobar(f"{momento}: ninguna petición a Google ni a Meta (sin peticiones no hay cookies de terceros)", not n.seguimiento(), str(n.seguimiento()))


def auditoria_antes_y_rechazar(n):
    sin_stripe, carrito = PAGINAS[:-1], PAGINAS[-1]
    recorrer(n, sin_stripe)
    sin_nada_de_google_ni_meta(n, f"Antes de aceptar ({len(sin_stripe)} páginas: portada, tienda, ficha y categoría)")
    recorrer(n, [carrito])
    sin_nada_de_google_ni_meta(n, "Antes de aceptar, ya en el carrito", con_stripe=True)
    n.clic('[data-cky-tag="reject-button"]')
    n.esperar(4)
    c = consentimiento(n)
    comprobar("Se ha pulsado «Rechazar» y queda registrado", c.get("action") == "yes" and c.get("analytics") == "no" and c.get("advertisement") == "no", str(c))
    recorrer(n, PAGINAS)
    sin_nada_de_google_ni_meta(n, f"Tras rechazar ({len(PAGINAS)} páginas más, carrito incluido)", con_stripe=True)


def auditoria_aceptar(n):
    n.ir("/")
    sin_nada_de_google_ni_meta(n, "Justo antes de aceptar")
    n.clic('[data-cky-tag="accept-button"]')
    n.esperar(8)
    a = auditar(n)
    p = peticiones_google_meta(n)
    print(f"      Cookies tras aceptar: {', '.join(a['cookies'])}")
    google = [c for c in a["cookies"] if c == "_ga" or c.startswith("_ga_")]
    comprobar("Google Analytics se activa: se carga, guarda sus cookies (_ga y _ga_…) y envía la visita", bool(p["gtag.js"]) and "_ga" in google and f"_ga_{GA_ID[2:]}" in google and bool(p["collect"]), f"{google} {p['gtag.js']} {p['collect']}")
    comprobar("El píxel de Meta se activa: se carga, guarda su cookie (_fbp) y envía PageView", bool(p["fbevents"]) and "_fbp" in a["cookies"] and any(PIXEL_ID in u and "ev=PageView" in u for u in p["tr"]), f"{p['fbevents']} {a['cookies']} {p['tr']}")


print(f"Probando {BASE}")
caso("Auditoría: antes de aceptar y al rechazar", auditoria_antes_y_rechazar)
caso("Auditoría: al aceptar", auditoria_aceptar)
caso("Primera visita, sin tocar nada", primera_visita)
caso("Rechazar", rechazar)
caso("Aceptar", aceptar)
caso("Personalizar: solo analítica", solo_analitica)
caso("Aceptar y luego retirar el consentimiento", retirar_consentimiento)

print(f"\n{'Todo correcto.' if not fallos else f'{fallos} comprobación(es) con fallo.'}")
sys.exit(1 if fallos else 0)
