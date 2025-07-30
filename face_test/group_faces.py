# -*- coding: utf-8 -*-
import sys
sys.stdout.reconfigure(encoding='utf-8')


import os
import cv2
import numpy as np
from insightface.model_zoo import arcface_onnx
from sklearn.metrics.pairwise import cosine_similarity

print("載入 ArcFace 模型中...")
model_path = "C:/Users/1311134007/.insightface/models/buffalo_l/w600k_r50.onnx"

rec_model = arcface_onnx.ArcFaceONNX(model_path)
rec_model.prepare(ctx_id=0, input_size=(112, 112))

# 設定路徑
face_dir = "faces"
group_dir = "group"
os.makedirs(group_dir, exist_ok=True)

embeddings = []
face_paths = []

# 讀取臉部圖片並提取特徵
for fname in sorted(os.listdir(face_dir)):
    if not fname.lower().endswith(".jpg"):
        continue
    fpath = os.path.join(face_dir, fname)
    img = cv2.imread(fpath)
    h, w = img.shape[:2]
    print(f"{fname} 圖片尺寸: {w}x{h}")

    if w < 60 or h < 60:
        print(f"圖片過小，跳過: {fname}")
        continue

    aligned = cv2.resize(img, (112, 112))
    embedding = rec_model.get_feat(aligned)
    if embedding is None:
        print(f"特徵擷取失敗: {fname}")
        continue

    embeddings.append(embedding.flatten())
    face_paths.append(fpath)
    print(f"特徵向量擷取成功: {fname}")

if not embeddings:
    print("沒有特徵資料，結束")
    exit()

# 計算 cosine 相似度矩陣
X = np.array(embeddings)
sim_matrix = cosine_similarity(X)
print("\n特徵相似度矩陣:")
np.set_printoptions(precision=2, suppress=True)
print(sim_matrix)

# 圖論分群
threshold = 0.4
n = len(sim_matrix)
adj = [[] for _ in range(n)]

for i in range(n):
    for j in range(i + 1, n):
        if sim_matrix[i][j] >= threshold:
            adj[i].append(j)
            adj[j].append(i)

# DFS 群組
visited = [False] * n
groups = []

def dfs(i, group):
    visited[i] = True
    group.append(i)
    for j in adj[i]:
        if not visited[j]:
            dfs(j, group)

for i in range(n):
    if not visited[i]:
        group = []
        dfs(i, group)
        groups.append(group)

# 儲存分群圖片
print("\n分群結果:")
for idx, group in enumerate(groups):
    group_path = os.path.join(group_dir, f"people_{idx+1}")
    os.makedirs(group_path, exist_ok=True)
    print(f"群組 {idx+1} ：{len(group)} 張臉")
    for i in group:
        fname = os.path.basename(face_paths[i])
        dst = os.path.join(group_path, fname)
        cv2.imwrite(dst, cv2.imread(face_paths[i]))

print(f"\n完成，共分 {len(groups)} 群")
