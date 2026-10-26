"""Un Chrome sin ventana, controlado por el protocolo de depuración, para las pruebas (scripts/probar-*.py).

Necesita Google Chrome y el módulo `websocket-client` (pip install websocket-client).
"""
import json
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.request

import websocket

SEGUIMIENTO = (
    "googletagmanager.com", "google-analytics.com", "analytics.google.com", "doubleclick.net",
    "facebook.com", "facebook.net", "google.com/ccm", "googleadservices.com",
)
BLOQUEAR = [
    "*google-analytics.com/*collect*", "*analytics.google.com/*collect*", "*facebook.com/tr*",
    "*doubleclick.net/*", "*googleadservices.com/*", "*google.com/ccm/collect*", "*google.com/pagead/*",
]


def puerto_libre():
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


class Navegador:
    """Un Chrome sin ventana con un perfil nuevo, controlado por el protocolo de depuración."""

    def __init__(self, base):
        self.base = base.rstrip("/")
        self.perfil = tempfile.mkdtemp(prefix="tostela-cookies-")
        puerto = puerto_libre()
        self.proceso = subprocess.Popen(
            [
                "google-chrome", "--headless=new", "--no-sandbox", "--disable-gpu",
                f"--remote-debugging-port={puerto}", f"--user-data-dir={self.perfil}",
                "--window-size=1366,900", "--no-first-run", "about:blank",
            ],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        )
        pagina = None
        for _ in range(60):
            try:
                pestanas = json.load(urllib.request.urlopen(f"http://127.0.0.1:{puerto}/json"))
                pagina = next(t for t in pestanas if t["type"] == "page")
                break
            except Exception:
                time.sleep(0.25)
        if not pagina:
            raise RuntimeError("Chrome no ha arrancado")
        self.ws = websocket.create_connection(pagina["webSocketDebuggerUrl"], timeout=30, suppress_origin=True)
        self.numero = 0
        self.peticiones = []
        self.cargada = False
        for metodo in ("Network.enable", "Page.enable", "Runtime.enable"):
            self.enviar(metodo)
        # Las peticiones de envío de datos se detienen y se rechazan: se ve el intento (con su URL) pero no sale nada.
        self.enviar("Fetch.enable", {"patterns": [{"urlPattern": p, "requestStage": "Request"} for p in BLOQUEAR]})
        # El píxel de Meta ignora a «HeadlessChrome» (lo toma por un robot): se presenta como un Chrome normal.
        agente = self.js("navigator.userAgent").replace("HeadlessChrome", "Chrome")
        self.enviar("Network.setUserAgentOverride", {"userAgent": agente})

    def _procesar(self, mensaje):
        metodo = mensaje.get("method")
        if metodo == "Network.requestWillBeSent":
            self.peticiones.append(mensaje["params"]["request"]["url"])
        elif metodo == "Fetch.requestPaused":
            parametros = mensaje["params"]
            self.peticiones.append(parametros["request"]["url"])
            self.numero += 1
            self.ws.send(json.dumps({
                "id": self.numero, "method": "Fetch.failRequest",
                "params": {"requestId": parametros["requestId"], "errorReason": "BlockedByClient"},
            }))
        elif metodo == "Page.loadEventFired":
            self.cargada = True

    def enviar(self, metodo, parametros=None):
        self.numero += 1
        mi_id = self.numero
        self.ws.send(json.dumps({"id": mi_id, "method": metodo, "params": parametros or {}}))
        while True:
            mensaje = json.loads(self.ws.recv())
            if mensaje.get("id") == mi_id:
                return mensaje.get("result", {})
            self._procesar(mensaje)

    def esperar(self, segundos):
        limite = time.time() + segundos
        self.ws.settimeout(0.4)
        while time.time() < limite:
            try:
                self._procesar(json.loads(self.ws.recv()))
            except websocket.WebSocketTimeoutException:
                pass
        self.ws.settimeout(30)

    def ir(self, ruta):
        self.cargada = False
        self.enviar("Page.navigate", {"url": self.base + ruta})
        limite = time.time() + 20
        while not self.cargada and time.time() < limite:
            self.esperar(0.5)
        self.esperar(3.5)

    def js(self, codigo):
        resultado = self.enviar("Runtime.evaluate", {"expression": codigo, "returnByValue": True})
        return resultado.get("result", {}).get("value")

    def clic(self, selector):
        return self.js(f"(function(){{var e=document.querySelector({json.dumps(selector)});if(!e)return false;e.click();return true;}})()")

    def cookies(self):
        return {c["name"]: c["value"] for c in self.enviar("Network.getAllCookies")["cookies"]}

    def seguimiento(self):
        return [u for u in self.peticiones if any(d in u for d in SEGUIMIENTO)]

    def data_layer(self):
        return json.loads(self.js("JSON.stringify((window.dataLayer||[]).map(function(a){return Array.prototype.slice.call(a)}))") or "[]")

    def cerrar(self):
        try:
            self.ws.close()
        finally:
            self.proceso.terminate()
            try:
                self.proceso.wait(timeout=10)
            except subprocess.TimeoutExpired:
                self.proceso.kill()
            shutil.rmtree(self.perfil, ignore_errors=True)
