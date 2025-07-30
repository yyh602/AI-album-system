# detect_faces_opencv.py
import cv2
import os

input_path = 'test(3).jpg'
output_dir = 'faces'

# 建立資料夾
os.makedirs(output_dir, exist_ok=True)

# 讀圖 + 灰階處理
img = cv2.imread(input_path)
gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

# 使用預設 haarcascade 模型
face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')

# 偵測臉（可微調參數）
faces = face_cascade.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(30, 30))

print(f"✅ 偵測到 {len(faces)} 張臉")

# 儲存每張臉
for i, (x, y, w, h) in enumerate(faces, start=1):
    face_img = img[y:y+h, x:x+w]
    face_path = os.path.join(output_dir, f'face_{i:03d}.jpg')
    cv2.imwrite(face_path, face_img)
    print(f"✅ 已儲存：{face_path}")
