import cv2
import numpy as np

INPUT_IMAGE = 'resized.jpg'  # ← 改成壓縮後的圖片
OUTPUT_IMAGE = 'enhanced.jpg'

image = cv2.imread(INPUT_IMAGE)
if image is None:
    print(f"❌ 無法讀取圖片：{INPUT_IMAGE}")
    exit(1)

# 色彩增強
lab = cv2.cvtColor(image, cv2.COLOR_BGR2LAB)
l, a, b = cv2.split(lab)
clahe = cv2.createCLAHE(clipLimit=3.0, tileGridSize=(8, 8))
cl = clahe.apply(l)
limg = cv2.merge((cl, a, b))
enhanced = cv2.cvtColor(limg, cv2.COLOR_LAB2BGR)

cv2.imwrite(OUTPUT_IMAGE, enhanced)
print(f"✅ 已儲存強化圖片：{OUTPUT_IMAGE}")
