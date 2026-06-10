import cv2

cap = cv2.VideoCapture(1)

print("Abierta:", cap.isOpened())

while True:

    ret, frame = cap.read()

    if not ret:
        print("Error leyendo frame")
        break

    cv2.imshow("Camara", frame)

    tecla = cv2.waitKey(1)

    if tecla == 27:
        break

cap.release()
cv2.destroyAllWindows()