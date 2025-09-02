#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
改進的人臉分群腳本 - 提高分群準確度
使用更精確的相似度計算和動態閾值調整
"""

import sys
sys.stdout.reconfigure(encoding='utf-8')

import os
import numpy as np
from sklearn.metrics.pairwise import cosine_similarity
from sklearn.cluster import DBSCAN
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

# 類別包裝修正 insightface
try:
    import onnxruntime as ort
    # 設定 providers
    ort.set_default_logger_severity(3)
    
    # 保存原始的 InferenceSession 類別
    original_inference_session_class = ort.InferenceSession
    
    # 創建修正版的 InferenceSession 類別
    class FixedInferenceSession(original_inference_session_class):
        def __init__(self, *args, **kwargs):
            if 'providers' not in kwargs:
                kwargs['providers'] = ['CPUExecutionProvider']
            super().__init__(*args, **kwargs)
    
    # 替換全域的 InferenceSession 類別
    ort.InferenceSession = FixedInferenceSession
    
    from insightface.model_zoo import arcface_onnx
    print("✅ insightface 載入成功（已修正 InferenceSession 類別）")
    USE_INSIGHTFACE = True
except ImportError as e:
    print(f"⚠️ insightface 無法載入: {e}，將使用備用方案")
    USE_INSIGHTFACE = False

def extract_features_with_insightface(img_path):
    """使用 insightface 提取特徵（改進版）"""
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
        
        # 使用 insightface 提取特徵
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
    """備用特徵提取方案（改進版）"""
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
        
        # 調整大小為標準尺寸
        gray = cv2.resize(gray, (64, 64))
        
        # 提取 HOG 特徵
        win_size = (64, 64)
        cell_size = (8, 8)
        block_size = (16, 16)
        block_stride = (8, 8)
        num_bins = 9
        
        hog = cv2.HOGDescriptor(win_size, block_size, block_stride, cell_size, num_bins)
        features = hog.compute(gray)
        
        return features.flatten()
        
    except Exception as e:
        print(f"備用特徵提取失敗: {e}")
        return None

def calculate_optimal_threshold(similarity_matrix):
    """計算最佳相似度閾值"""
    # 獲取所有相似度值（排除對角線）
    similarities = []
    for i in range(len(similarity_matrix)):
        for j in range(i + 1, len(similarity_matrix)):
            similarities.append(similarity_matrix[i][j])
    
    if not similarities:
        return 0.5
    
    similarities = np.array(similarities)
    
    # 使用多種方法計算閾值
    mean_sim = np.mean(similarities)
    std_sim = np.std(similarities)
    median_sim = np.median(similarities)
    
    # 方法1：基於均值和標準差
    threshold1 = mean_sim + 0.5 * std_sim
    
    # 方法2：基於百分位數
    threshold2 = np.percentile(similarities, 75)
    
    # 方法3：基於中位數
    threshold3 = median_sim + 0.3 * std_sim
    
    # 選擇最保守的閾值（最高值）
    optimal_threshold = max(threshold1, threshold2, threshold3, 0.6)
    
    print(f"相似度統計: 均值={mean_sim:.3f}, 標準差={std_sim:.3f}, 中位數={median_sim:.3f}")
    print(f"計算的閾值: {optimal_threshold:.3f}")
    
    return optimal_threshold

def group_faces_improved():
    """改進的人臉分群函數"""
    faces_dir = 'faces'
    group_dir = 'group'
    
    if not os.path.exists(faces_dir):
        print(f"faces 目錄不存在: {faces_dir}")
        return []
    
    # 清理舊的群組目錄
    if os.path.exists(group_dir):
        shutil.rmtree(group_dir)
    os.makedirs(group_dir, exist_ok=True)
    
    # 獲取所有人臉檔案
    face_files = [f for f in os.listdir(faces_dir) if f.lower().endswith(('.jpg', '.jpeg', '.png'))]
    face_files.sort()
    
    if not face_files:
        print("沒有找到人臉檔案")
        return []
    
    print(f"找到 {len(face_files)} 張人臉")
    
    # 提取特徵
    embeddings = []
    face_paths = []
    
    for fname in face_files:
        fpath = os.path.join(faces_dir, fname)
        
        try:
            print(f"處理: {fname}")
            
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
    np.set_printoptions(precision=3, suppress=True)
    print(sim_matrix)
    
    # 計算最佳閾值
    optimal_threshold = calculate_optimal_threshold(sim_matrix)
    
    # 使用 DBSCAN 進行分群（更精確）
    print(f"\n使用 DBSCAN 分群，閾值: {optimal_threshold}")
    
    # 將相似度轉換為距離
    distance_matrix = 1 - sim_matrix
    
    # 使用 DBSCAN
    clustering = DBSCAN(eps=1-optimal_threshold, min_samples=1, metric='precomputed')
    labels = clustering.fit_predict(distance_matrix)
    
    # 整理分群結果
    unique_labels = set(labels)
    groups = []
    
    for label in unique_labels:
        if label == -1:  # 噪聲點，每個作為獨立群組
            noise_indices = np.where(labels == label)[0]
            for idx in noise_indices:
                groups.append([idx])
        else:
            group_indices = np.where(labels == label)[0]
            groups.append(group_indices.tolist())
    
    # 按群組大小排序（大的群組在前）
    groups.sort(key=len, reverse=True)
    
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
        
        print(f"群組 {idx+1} ({group_name})：{len(group)} 張臉")
        
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
        print("=== 改進的人臉分群系統啟動 ===")
        print(f"OpenCV 可用: {OPENCV_AVAILABLE}")
        print(f"insightface 可用: {USE_INSIGHTFACE}")
        
        # 初始化 insightface 模型
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
        results = group_faces_improved()
        print("✅ 改進的人臉分群成功完成")
        print(f"群組數量: {len(results)}")
        
        for group in results:
            print(f"- {group['group_name']}: {len(group['faces'])} 張人臉")
            
    except Exception as e:
        print(f"❌ 人臉分群失敗: {e}")
        exit(1) 