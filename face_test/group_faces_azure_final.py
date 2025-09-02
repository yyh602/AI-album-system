#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
使用 insightface 的人臉分群腳本 - 最終修正版
直接修改 insightface 的 InferenceSession 調用
"""

import sys
sys.stdout.reconfigure(encoding='utf-8')

import os
import numpy as np
from sklearn.metrics.pairwise import cosine_similarity
import json
import shutil

# 修正 onnxruntime providers 問題
os.environ['ORT_PROVIDERS'] = 'CPUExecutionProvider'

# 嘗試載入 OpenCV
try:
    import cv2
    print("✅ OpenCV 載入成功")
    OPENCV_AVAILABLE = True
except ImportError:
    print("❌ OpenCV 無法載入")
    OPENCV_AVAILABLE = False

# 修正 insightface 的 InferenceSession 調用
try:
    import onnxruntime as ort
    # 設定 providers
    ort.set_default_logger_severity(3)
    
    # 直接修改 insightface 的 InferenceSession 調用
    import insightface.model_zoo.arcface_onnx
    
    # 保存原始的 InferenceSession
    original_inference_session = ort.InferenceSession
    
    # 創建修正版的 InferenceSession
    def fixed_inference_session(*args, **kwargs):
        if 'providers' not in kwargs:
            kwargs['providers'] = ['CPUExecutionProvider']
        return original_inference_session(*args, **kwargs)
    
    # 替換 insightface 中的 InferenceSession
    insightface.model_zoo.arcface_onnx.ort.InferenceSession = fixed_inference_session
    
    from insightface.model_zoo import arcface_onnx
    print("✅ insightface 載入成功（已修正 InferenceSession）")
    USE_INSIGHTFACE = True
except ImportError as e:
    print(f"⚠️ insightface 無法載入: {e}，將使用備用方案")
    USE_INSIGHTFACE = False

def extract_features_with_insightface(img_path):
    """使用 insightface 提取特徵（最終修正版）"""
    try:
        if not OPENCV_AVAILABLE:
            print("OpenCV 不可用，無法使用 insightface")
            return None
            
        # 載入圖片
        img = cv2.imread(img_path)
        if img is None:
            return None
        
        h, w = img.shape[:2]
        
        # 調整大小為 112x112
        aligned = cv2.resize(img, (112, 112))
        
        # 使用 insightface 提取特徵（最終修正版）
        if rec_model is not None:
            embedding = rec_model.get_feat(aligned)
            if embedding is None:
                return None
            return embedding.flatten()
        else:
            print("rec_model 未初始化")
            return None
        
    except Exception as e:
        print(f"insightface 特徵提取失敗: {e}")
        return None

def extract_features_fallback(img_path):
    """備用特徵提取方案"""
    try:
        if not OPENCV_AVAILABLE:
            print("OpenCV 不可用，無法提取特徵")
            return None
            
        # 載入圖片
        img = cv2.imread(img_path)
        if img is None:
            return None
        
        # 轉換為灰度圖
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # 調整大小
        resized = cv2.resize(gray, (112, 112))
        
        # 標準化
        normalized = resized.astype(np.float32) / 255.0
        
        # 提取基本特徵
        features = []
        
        # 整體統計特徵
        features.extend([
            np.mean(normalized),
            np.std(normalized),
            np.percentile(normalized, 25),
            np.percentile(normalized, 75),
            np.max(normalized),
            np.min(normalized)
        ])
        
        # 網格特徵
        h, w = normalized.shape
        grid_size = 4
        cell_h, cell_w = h // grid_size, w // grid_size
        
        for i in range(grid_size):
            for j in range(grid_size):
                cell = normalized[i*cell_h:(i+1)*cell_h, j*cell_w:(j+1)*cell_w]
                features.extend([
                    np.mean(cell),
                    np.std(cell),
                    np.max(cell),
                    np.min(cell)
                ])
        
        # 邊緣特徵
        grad_x = np.diff(normalized, axis=1)
        grad_y = np.diff(normalized, axis=0)
        
        features.extend([
            np.mean(np.abs(grad_x)),
            np.std(grad_x),
            np.mean(np.abs(grad_y)),
            np.std(grad_y)
        ])
        
        return np.array(features)
        
    except Exception as e:
        print(f"備用特徵提取失敗: {e}")
        return None

def group_faces():
    """主要的人臉分群函數"""
    
    # 設定路徑
    face_dir = "faces"
    group_dir = "group"
    
    # 清理並創建 group 目錄
    if os.path.exists(group_dir):
        shutil.rmtree(group_dir)
    os.makedirs(group_dir, exist_ok=True)
    
    embeddings = []
    face_paths = []
    
    # 讀取臉部圖片並提取特徵
    face_files = [f for f in sorted(os.listdir(face_dir)) if f.lower().endswith('.jpg')]
    
    if not face_files:
        print("沒有人臉檔案")
        return []
    
    print(f"找到 {len(face_files)} 個人臉檔案")
    
    for fname in face_files:
        fpath = os.path.join(face_dir, fname)
        
        try:
            # 檢查圖片尺寸
            if OPENCV_AVAILABLE:
                img = cv2.imread(fpath)
                if img is None:
                    print(f"無法載入圖片: {fname}")
                    continue
                    
                h, w = img.shape[:2]
                print(f"{fname} 圖片尺寸: {w}x{h}")
                
                if w < 60 or h < 60:
                    print(f"圖片過小，跳過: {fname}")
                    continue
            else:
                # 如果 OpenCV 不可用，使用檔案大小檢查
                file_size = os.path.getsize(fpath)
                if file_size < 1000:  # 小於 1KB
                    print(f"檔案過小，跳過: {fname}")
                    continue
                print(f"{fname} 檔案大小: {file_size} bytes")
            
            # 提取特徵
            if USE_INSIGHTFACE and OPENCV_AVAILABLE:
                embedding = extract_features_with_insightface(fpath)
            elif OPENCV_AVAILABLE:
                embedding = extract_features_fallback(fpath)
            else:
                print(f"無法提取特徵，跳過: {fname}")
                continue
            
            if embedding is None:
                print(f"特徵擷取失敗: {fname}")
                continue
            
            embeddings.append(embedding)
            face_paths.append(fpath)
            print(f"特徵向量擷取成功: {fname} (維度: {len(embedding)})")
            
        except Exception as e:
            print(f"處理 {fname} 時出錯: {e}")
            continue
    
    if not embeddings:
        print("沒有特徵資料，結束")
        return []
    
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
    
    # 儲存分群結果
    print("\n分群結果:")
    group_results = []
    
    for idx, group in enumerate(groups):
        group_name = f"people_{idx+1}"
        group_path = os.path.join(group_dir, group_name)
        os.makedirs(group_path, exist_ok=True)
        
        group_info = {
            'group_name': group_name,
            'faces': []
        }
        
        print(f"群組 {idx+1} ：{len(group)} 張臉")
        
        for i in group:
            fname = os.path.basename(face_paths[i])
            dst = os.path.join(group_path, fname)
            
            try:
                # 複製檔案
                if OPENCV_AVAILABLE:
                    img = cv2.imread(face_paths[i])
                    cv2.imwrite(dst, img)
                else:
                    shutil.copy2(face_paths[i], dst)
                
                # 獲取檔案資訊
                file_size = os.path.getsize(face_paths[i])
                
                group_info['faces'].append({
                    'filename': fname,
                    'size': file_size,
                    'path': dst
                })
                
                print(f"  - 已複製 {fname}")
                
            except Exception as e:
                print(f"  - 複製 {fname} 失敗: {e}")
        
        group_results.append(group_info)
    
    # 儲存分群結果到 JSON
    with open('group_results.json', 'w', encoding='utf-8') as f:
        json.dump(group_results, f, ensure_ascii=False, indent=2)
    
    print(f"\n完成，共分 {len(groups)} 群")
    
    return group_results

# 全域變數
rec_model = None

if __name__ == "__main__":
    try:
        print("=== 人臉分群系統啟動（最終修正版）===")
        print(f"OpenCV 可用: {OPENCV_AVAILABLE}")
        print(f"insightface 可用: {USE_INSIGHTFACE}")
        
        # 初始化 insightface 模型（最終修正版）
        if USE_INSIGHTFACE and OPENCV_AVAILABLE:
            print("載入 ArcFace 模型中...")
            try:
                # 嘗試多個可能的模型路徑
                model_paths = [
                    "/home/site/wwwroot/face_test/models/buffalo_l/w600k_r50.onnx",
                    "/home/site/wwwroot/face_test/w600k_r50.onnx",
                    "/tmp/w600k_r50.onnx"
                ]
                
                model_loaded = False
                for model_path in model_paths:
                    if os.path.exists(model_path):
                        print(f"找到模型: {model_path}")
                        
                        # 最終修正版：使用修正的 InferenceSession
                        rec_model = arcface_onnx.ArcFaceONNX(model_path)
                        rec_model.prepare(ctx_id=0, input_size=(112, 112))
                        
                        model_loaded = True
                        print("✅ insightface 模型載入成功！")
                        break
                
                if not model_loaded:
                    print("⚠️ 找不到 insightface 模型檔案，將使用備用方案")
                    USE_INSIGHTFACE = False
                    
            except Exception as e:
                print(f"⚠️ insightface 模型載入失敗: {e}，將使用備用方案")
                USE_INSIGHTFACE = False
        
        # 執行人臉分群
        results = group_faces()
        print("✅ 人臉分群成功完成")
        print(f"群組數量: {len(results)}")
        
        for group in results:
            print(f"- {group['group_name']}: {len(group['faces'])} 張人臉")
            
    except Exception as e:
        print(f"❌ 人臉分群失敗: {e}")
        exit(1)
