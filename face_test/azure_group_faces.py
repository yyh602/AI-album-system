# -*- coding: utf-8 -*-
import sys
import os
import cv2
import numpy as np
import json
import requests
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

# 讀取人臉對應關係
face_map = {}
if os.path.exists('face_map.json'):
    with open('face_map.json', 'r', encoding='utf-8') as f:
        face_map = json.load(f)

embeddings = []
face_paths = []
face_names = []

# 讀取臉部圖片並提取特徵
for fname in sorted(os.listdir(face_dir)):
    if not fname.lower().endswith(".jpg"):
        continue
    
    fpath = os.path.join(face_dir, fname)
    
    # 嘗試從本地讀取，如果失敗則從 Azure 下載
    img = cv2.imread(fpath)
    if img is None and fname in face_map and face_map[fname].get('azure_url'):
        print(f"從 Azure 下載圖片: {fname}")
        try:
            response = requests.get(face_map[fname]['azure_url'])
            if response.status_code == 200:
                # 將圖片資料寫入臨時檔案
                temp_path = os.path.join(face_dir, f"temp_{fname}")
                with open(temp_path, 'wb') as f:
                    f.write(response.content)
                img = cv2.imread(temp_path)
                # 清理臨時檔案
                os.remove(temp_path)
        except Exception as e:
            print(f"下載失敗: {fname} - {e}")
            continue
    
    if img is None:
        print(f"無法讀取圖片: {fname}")
        continue
    
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
    face_names.append(fname)
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
        fname = face_names[i]
        src_path = face_paths[i]
        dst_path = os.path.join(group_path, fname)
        
        # 複製圖片到分群目錄
        cv2.imwrite(dst_path, cv2.imread(src_path))
        
        # 如果原始圖片在 Azure 上，也記錄 Azure URL
        if fname in face_map and face_map[fname].get('azure_url'):
            print(f"  - {fname} (Azure: {face_map[fname]['azure_url']})")
        else:
            print(f"  - {fname}")

print(f"\n完成，共分 {len(groups)} 群")

# 儲存分群詳細資訊
group_details = {}
for idx, group in enumerate(groups):
    group_name = f"people_{idx+1}"
    group_details[group_name] = []
    
    for i in group:
        fname = face_names[i]
        face_info = {
            'face_name': fname,
            'local_path': face_paths[i],
            'original_image': face_map.get(fname, {}).get('original_image', ''),
            'azure_url': face_map.get(fname, {}).get('azure_url', '')
        }
        group_details[group_name].append(face_info)

# 儲存分群詳細資訊到 JSON 檔案
with open('group_details.json', 'w', encoding='utf-8') as f:
    json.dump(group_details, f, ensure_ascii=False, indent=2)

print("分群詳細資訊已儲存到 group_details.json")
