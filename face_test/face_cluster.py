import os
import shutil
import cv2
import numpy as np
from insightface.app import FaceAnalysis
from sklearn.cluster import DBSCAN

# ===== 參數 =====
faces_dir = 'faces'        # 存放人臉裁切圖的資料夾
output_dir = 'clusters'    # 輸出分群後的人臉資料夾
min_cluster_size = 1       # 至少幾張圖才能成為一群（1表示單張也保留）

# ===== 初始化 InsightFace 模型 =====
app = FaceAnalysis(name="buffalo_l", providers=['CPUExecutionProvider'])
app.prepare(ctx_id=0)

# ===== 掃描人臉圖像 =====
embeddings = []
filenames = []

for file in os.listdir(faces_dir):
    if not file.lower().endswith(('.jpg', '.jpeg', '.png')):
        continue

    path = os.path.join(faces_dir, file)
    img = cv2.imread(path)

    faces = app.get(img)
    if len(faces) == 0:
        print(f"❌ 無法偵測人臉：{file}")
        continue

    # 只取最明顯的一張人臉
    emb = faces[0].embedding
    embeddings.append(emb)
    filenames.append(file)

print(f"✅ 共載入 {len(filenames)} 張人臉進行分群")

# ===== 進行 DBSCAN 分群 =====
embeddings = np.array(embeddings)
clustering = DBSCAN(eps=0.45, min_samples=min_cluster_size, metric='euclidean').fit(embeddings)
labels = clustering.labels_

# ===== 建立群組資料夾並複製圖片 =====
if not os.path.exists(output_dir):
    os.makedirs(output_dir)

cluster_count = len(set(labels)) - (1 if -1 in labels else 0)

for label, file in zip(labels, filenames):
    cluster_name = f"person_{label}" if label != -1 else "unknown"
    cluster_path = os.path.join(output_dir, cluster_name)
    os.makedirs(cluster_path, exist_ok=True)
    shutil.copy(os.path.join(faces_dir, file), os.path.join(cluster_path, file))

print(f"📂 已建立 {cluster_count} 群（含未知 {list(labels).count(-1)} 張）")
