import cv2
import json
import os
import math

INPUT_IMAGE = 'test(3).jpg'
BOX_FILE = 'face_boxes.json'
OUTPUT_DIR = 'subimages'
MAX_FACES_PER_IMAGE = 10

# 載入圖片與人臉座標
img = cv2.imread(INPUT_IMAGE)
if img is None:
    print("❌ 無法讀取圖片")
    exit()

h, w = img.shape[:2]

with open(BOX_FILE, 'r') as f:
    boxes = json.load(f)

# 排除重疊的人臉
def iou(boxA, boxB):
    xa1, ya1, xa2, ya2 = boxA
    xb1, yb1, xb2, yb2 = boxB

    interX1 = max(xa1, xb1)
    interY1 = max(ya1, yb1)
    interX2 = min(xa2, xb2)
    interY2 = min(ya2, yb2)

    interArea = max(0, interX2 - interX1) * max(0, interY2 - interY1)
    areaA = (xa2 - xa1) * (ya2 - ya1)
    areaB = (xb2 - xb1) * (yb2 - yb1)
    unionArea = areaA + areaB - interArea

    if unionArea == 0:
        return 0
    return interArea / unionArea

filtered = []
for box in boxes:
    skip = False
    for kept in filtered:
        if iou(box, kept) > 0.3:
            skip = True
            break
    if not skip:
        filtered.append(box)

print(f"✅ 去重後人臉數量：{len(filtered)}")

# 建立資料夾
if not os.path.exists(OUTPUT_DIR):
    os.makedirs(OUTPUT_DIR)

# 將圖片切成多張，每張不超過 MAX_FACES_PER_IMAGE
chunks = [filtered[i:i + MAX_FACES_PER_IMAGE] for i in range(0, len(filtered), MAX_FACES_PER_IMAGE)]

for idx, chunk in enumerate(chunks, 1):
    # 根據人臉座標切割出邊界外加 padding
    min_x = max(0, min([b[0] for b in chunk]) - 30)
    min_y = max(0, min([b[1] for b in chunk]) - 30)
    max_x = min(w, max([b[2] for b in chunk]) + 30)
    max_y = min(h, max([b[3] for b in chunk]) + 30)

    cropped = img[min_y:max_y, min_x:max_x]
    out_path = os.path.join(OUTPUT_DIR, f"sub_{idx:02d}.jpg")
    cv2.imwrite(out_path, cropped)
    print(f"📸 儲存：{out_path}")
