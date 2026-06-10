import cv2
import torch
import time
import threading
import pymysql

from flask import Flask, Response, jsonify, request
from flask_cors import CORS
from ultralytics import YOLO

# =========================================================
# CONFIGURACION BASE DE DATOS
# =========================================================

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'control_acceso',
    'charset': 'utf8mb4'
}

# =========================================================
# CONFIGURACION IA
# =========================================================

FRAME_SKIP = 2
CONF_THRESHOLD = 0.25

LINEA_X = 0.50
TIEMPO_CONTEO = 30

# =========================================================
# VARIABLES GLOBALES
# =========================================================

frame_actual = None
frame_count = 0

# Conteos globales
conteo = {
    "entradas": 0,
    "salidas": 0,
    "personas_dentro": 0
}

# Estado sistema
contador_activo = False
modo_actual = None  # ENTRADA o SALIDA

tiempo_fin_conteo = 0

# Tracks
tracks = {}

# =========================================================
# FLASK
# =========================================================

app = Flask(__name__)
CORS(app)

# =========================================================
# CARGAR MODELO
# =========================================================

print("====================================")
print("Cargando modelo YOLO...")
print("====================================")

device = "cuda" if torch.cuda.is_available() else "cpu"

model = YOLO("yolov8n.pt")
model.to(device)

print(f"Modelo cargado en: {device}")

# =========================================================
# BASE DE DATOS
# =========================================================

def guardar_evento(tipo, cantidad):

    try:

        conn = pymysql.connect(**DB_CONFIG)
        cursor = conn.cursor()

        sql = """
        INSERT INTO historial_accesos
        (resultado_acceso, personas_validacion)
        VALUES (%s, %s)
        """

        cursor.execute(sql, (tipo, cantidad))

        conn.commit()

        cursor.close()
        conn.close()

        print(f"✅ Evento guardado: {tipo} -> {cantidad}")

    except Exception as e:
        print(f"❌ Error BD: {e}")

# =========================================================
# API
# =========================================================

@app.route('/api/validacion', methods=['POST'])
def recibir_validacion():

    global contador_activo
    global modo_actual
    global tiempo_fin_conteo
    global tracks

    data = request.get_json()

    if not data:
        return jsonify({
            "status": "error",
            "mensaje": "Sin datos"
        }), 400

    tipo = data.get("tipo")

    if tipo not in ["ENTRADA", "SALIDA"]:

        return jsonify({
            "status": "error",
            "mensaje": "Tipo invalido"
        }), 400

    # Reiniciar tracking
    tracks = {}

    # Activar conteo
    modo_actual = tipo
    contador_activo = True

    tiempo_fin_conteo = time.time() + TIEMPO_CONTEO

    print("====================================")
    print(f"🚪 VALIDACION: {tipo}")
    print(f"⏱ Conteo activo por {TIEMPO_CONTEO} segundos")
    print("====================================")

    return jsonify({
        "status": "ok",
        "modo": modo_actual,
        "tiempo": TIEMPO_CONTEO
    })

# =========================================================
# API CONTEO
# =========================================================

@app.route('/api/conteo')
def api_conteo():

    restante = 0

    if contador_activo:
        restante = int(tiempo_fin_conteo - time.time())

        if restante < 0:
            restante = 0

    return jsonify({
        "entradas": conteo["entradas"],
        "salidas": conteo["salidas"],
        "personas_dentro": conteo["personas_dentro"],
        "contador_activo": contador_activo,
        "modo_actual": modo_actual,
        "tiempo_restante": restante
    })

# =========================================================
# VIDEO STREAM
# =========================================================

@app.route('/video_feed')
def video_feed():
    return Response(
        generar_frames(),
        mimetype='multipart/x-mixed-replace; boundary=frame'
    )

# =========================================================
# GENERAR STREAM
# =========================================================

def generar_frames():

    global frame_actual

    while True:

        if frame_actual is not None:

            _, jpeg = cv2.imencode('.jpg', frame_actual)

            yield (
                b'--frame\r\n'
                b'Content-Type: image/jpeg\r\n\r\n' +
                jpeg.tobytes() +
                b'\r\n\r\n'
            )

        time.sleep(0.03)

# =========================================================
# PROCESAMIENTO IA
# =========================================================

def procesar_camara():

    global frame_actual
    global frame_count
    global contador_activo
    global modo_actual
    global tiempo_fin_conteo
    global tracks
    print("====================================")
    print("Iniciando camara...")
    print("====================================")

    cap = cv2.VideoCapture(1, cv2.CAP_DSHOW)

    cap.set(cv2.CAP_PROP_FRAME_WIDTH, 1920)
    cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 1080)

    print("Camara abierta:", cap.isOpened())

    if not cap.isOpened():

        print("❌ No se pudo abrir la cámara")
        return

    ancho = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
    alto = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))

    linea_pos = int(ancho * LINEA_X)

    margen = int(ancho * 0.08)

    zona_izq = linea_pos - margen
    zona_der = linea_pos + margen

    print(f"Resolucion: {ancho}x{alto}")

    while True:

        ret, frame = cap.read()

        if not ret:
            print("❌ Error leyendo cámara")
            break

        output = frame.copy()

        frame_count += 1

        # =================================================
        # FINALIZAR CONTEO
        # =================================================

        if contador_activo and time.time() >= tiempo_fin_conteo:

            contador_activo = False

            print("====================================")
            print("🔒 Conteo finalizado")
            print("====================================")

            guardar_evento(
                modo_actual,
                conteo["personas_dentro"]
            )

            modo_actual = None

        # =================================================
        # DIBUJAR LINEA
        # =================================================

        cv2.line(
            output,
            (linea_pos, 0),
            (linea_pos, alto),
            (0, 255, 255),
            3
        )

        # =================================================
        # DETECCION
        # =================================================

        if contador_activo and frame_count % FRAME_SKIP == 0:

            resultados = model.track(
                frame,
                persist=True,
                classes=[0],
                conf=CONF_THRESHOLD,
                verbose=False
            )

            if resultados[0].boxes.id is not None:

                boxes = resultados[0].boxes.xyxy.cpu().numpy()

                ids = resultados[0].boxes.id.int().cpu().tolist()

                for box, track_id in zip(boxes, ids):

                    x1, y1, x2, y2 = map(int, box[:4])

                    cx = (x1 + x2) // 2
                    cy = (y1 + y2) // 2

                    # =====================================
                    # CREAR TRACK
                    # =====================================

                    if track_id not in tracks:

                        tracks[track_id] = {
                            "historial": [],
                            "contado": False
                        }

                    tracks[track_id]["historial"].append(cx)

                    historial = tracks[track_id]["historial"]

                    # Limitar historial
                    if len(historial) > 20:
                        historial.pop(0)

                    # =====================================
                    # DETECTAR CRUCE
                    # =====================================

                    if len(historial) >= 2:

                        inicio = historial[0]
                        fin = historial[-1]

                        # =================================
                        # ENTRADA
                        # =================================

                        if (
                            inicio < zona_izq and
                            fin > zona_der and
                            not tracks[track_id]["contado"]
                        ):

                            if modo_actual == "ENTRADA":

                                conteo["entradas"] += 1
                                conteo["personas_dentro"] += 1

                                print("✅ PERSONA ENTRO")

                            tracks[track_id]["contado"] = True

                        # =================================
                        # SALIDA
                        # =================================

                        elif (
                            inicio > zona_der and
                            fin < zona_izq and
                            not tracks[track_id]["contado"]
                        ):

                            if modo_actual == "SALIDA":

                                conteo["salidas"] += 1

                                if conteo["personas_dentro"] > 0:
                                    conteo["personas_dentro"] -= 1

                                print("⬅️ PERSONA SALIO")

                            tracks[track_id]["contado"] = True

                    # =====================================
                    # DIBUJAR
                    # =====================================

                    cv2.rectangle(
                        output,
                        (x1, y1),
                        (x2, y2),
                        (0, 255, 0),
                        2
                    )

                    cv2.circle(
                        output,
                        (cx, cy),
                        5,
                        (0, 0, 255),
                        -1
                    )

                    cv2.putText(
                        output,
                        f"ID:{track_id}",
                        (x1, y1 - 10),
                        cv2.FONT_HERSHEY_SIMPLEX,
                        0.5,
                        (0, 255, 0),
                        2
                    )

        # =================================================
        # TEXTO INFORMACION
        # =================================================

        cv2.putText(
            output,
            f"Entradas: {conteo['entradas']}",
            (10, 30),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.7,
            (0, 255, 0),
            2
        )

        cv2.putText(
            output,
            f"Salidas: {conteo['salidas']}",
            (10, 60),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.7,
            (0, 0, 255),
            2
        )

        cv2.putText(
            output,
            f"Dentro: {conteo['personas_dentro']}",
            (10, 90),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.7,
            (255, 255, 0),
            2
        )

        # =================================================
        # ESTADO
        # =================================================

        estado = "INACTIVO"

        if contador_activo:
            estado = f"CONTANDO {modo_actual}"

        cv2.putText(
            output,
            estado,
            (10, 130),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.7,
            (0, 255, 255),
            2
        )

        # =================================================
        # TIEMPO RESTANTE
        # =================================================

        if contador_activo:

            restante = int(tiempo_fin_conteo - time.time())

            cv2.putText(
                output,
                f"Tiempo: {restante}s",
                (10, 160),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.7,
                (255, 255, 255),
                2
            )

        frame_actual = output

    cap.release()

# =========================================================
# MAIN
# =========================================================

if __name__ == '__main__':

    print("====================================")
    print("INICIANDO SERVIDOR IA")
    print("====================================")

    hilo_ia = threading.Thread(
        target=procesar_camara,
        daemon=True
    )

    hilo_ia.start()

    app.run(
        host='0.0.0.0',
        port=5000,
        debug=False,
        threaded=True
    )

