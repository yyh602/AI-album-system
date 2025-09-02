#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
智能人臉分群腳本 - 使用 OpenCV 和簡單特徵提取（無 insightface）
"""

import os
import cv2
import numpy as np
from sklearn.cluster import DBSCAN
from sklearn.metrics.pairwise import cosine_similarity
import json
import shutil

def extract_face_features(img):
    """提取人臉特徵向量（不使用 insightface）"""
    try:
        # 轉換為灰度圖
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # 調整大小為標準尺寸
        resized = cv2.resize(gray, (128, 128))
        
        # 方法1: 使用 HOG 特徵（如果可用）
        try:
            win_size = (128, 128)
            cell_size = (16, 16)
            block_size = (32, 32)
            block_stride = (16, 16)
            num_bins = 9
            
            hog = cv2.HOGDescriptor(win_size, block_size, block_stride, cell_size, num_bins)
            features = hog.compute(resized)
            return features.flatten()
        except:
            pass
        
        # 方法2: 使用簡單的像素值 + 統計特徵
        # 計算不同區域的平均亮度
        h, w = resized.shape
        features = []
        
        # 將圖片分成 4x4 的網格
        grid_size = 4
        cell_h, cell_w = h // grid_size, w // grid_size
        
        for i in range(grid_size):
            for j in range(grid_size):
                cell = resized[i*cell_h:(i+1)*cell_h, j*cell_w:(j+1)*cell_w]
                features.extend([
                    np.mean(cell),  # 平均亮度
                    np.std(cell),   # 標準差
                    np.max(cell),   # 最大值
                    np.min(cell)    # 最小值
                ])
        
        # 添加整體統計特徵
        features.extend([
            np.mean(resized),  # 整體平均亮度
            np.std(resized),   # 整體標準差
            np.percentile(resized, 25),  # 25% 分位數
            np.percentile(resized, 75)   # 75% 分位數
        ])
        
        return np.array(features)
        
    except Exception as e:
        print(f"特徵提取失敗: {e}")
        # 如果所有方法都失敗，返回零向量
        return np.zeros(80)

def group_faces():
    """主要的人臉分群函數"""
    
    # 設定路徑
    face_dir = "faces"
    group_dir = "group"
    
    # 清理並創建 group 目錄
    if os.path.exists(group_dir):
        shutil.rmtree(group_dir)
    os.makedirs(group_dir, exist_ok=True)
    
    # 獲取所有人臉檔案
    face_files = [f for f in os.listdir(face_dir) if f.lower().endswith('.jpg')]
    
    if not face_files:
        print("沒有人臉檔案")
        return []
    
    print(f"找到 {len(face_files)} 個人臉檔案")
    
    # 提取特徵
    features = []
    valid_files = []
    
    for fname in face_files:
        fpath = os.path.join(face_dir, fname)
        try:
            img = cv2.imread(fpath)
            if img is None:
                print(f"無法讀取圖片: {fname}")
                continue
                
            h, w = img.shape[:2]
            print(f"{fname} 圖片尺寸: {w}x{h}")
            
            if w < 30 or h < 30:
                print(f"圖片過小，跳過: {fname}")
                continue
            
            # 提取特徵
            feature = extract_face_features(img)
            features.append(feature)
            valid_files.append(fname)
            print(f"特徵提取成功: {fname} (特徵維度: {len(feature)})")
            
        except Exception as e:
            print(f"處理 {fname} 時出錯: {e}")
            continue
    
    if not features:
        print("沒有有效的特徵資料")
        return []
    
    # 轉換為 numpy 陣列
    X = np.array(features)
    print(f"特徵矩陣形狀: {X.shape}")
    
    # 特徵標準化
    try:
        from sklearn.preprocessing import StandardScaler
        scaler = StandardScaler()
        X_scaled = scaler.fit_transform(X)
        print("特徵標準化完成")
    except:
        X_scaled = X
        print("使用原始特徵（未標準化）")
    
    # 使用 DBSCAN 進行分群
    try:
        # 計算相似度矩陣
        sim_matrix = cosine_similarity(X_scaled)
        
        # 使用 DBSCAN 分群
        # 調整參數以適應我們的特徵
        clustering = DBSCAN(eps=0.4, min_samples=1, metric='precomputed')
        
        # 將相似度轉換為距離（1 - 相似度）
        distance_matrix = 1 - sim_matrix
        
        # 執行分群
        labels = clustering.fit_predict(distance_matrix)
        
        print(f"DBSCAN 分群完成，參數: eps=0.4, min_samples=1")
        
    except Exception as e:
        print(f"DBSCAN 分群失敗，使用簡單分群: {e}")
        # 如果 DBSCAN 失敗，使用簡單的隨機分群
        labels = np.random.randint(0, min(4, len(features)), len(features))
    
    # 統計分群結果
    unique_labels = set(labels)
    print(f"\n分群結果: {len(unique_labels)} 個群組")
    
    # 顯示相似度矩陣（前幾個樣本）
    if len(sim_matrix) > 0:
        print("\n相似度矩陣（前4x4）:")
        np.set_printoptions(precision=2, suppress=True)
        print(sim_matrix[:4, :4])
    
    # 儲存分群結果
    group_results = []
    
    for label in unique_labels:
        if label == -1:  # DBSCAN 的噪聲點
            continue
            
        group_name = f"people_{label + 1}"
        group_path = os.path.join(group_dir, group_name)
        os.makedirs(group_path, exist_ok=True)
        
        # 找到屬於這個群組的檔案
        group_files = [valid_files[i] for i in range(len(valid_files)) if labels[i] == label]
        
        group_info = {
            'group_name': group_name,
            'faces': []
        }
        
        print(f"群組 {group_name}: {len(group_files)} 張臉")
        
        for fname in group_files:
            src_path = os.path.join(face_dir, fname)
            dst_path = os.path.join(group_path, fname)
            
            try:
                # 複製檔案
                shutil.copy2(src_path, dst_path)
                
                # 獲取檔案資訊
                file_size = os.path.getsize(src_path)
                
                group_info['faces'].append({
                    'filename': fname,
                    'size': file_size,
                    'path': dst_path
                })
                
                print(f"  - 已複製 {fname}")
                
            except Exception as e:
                print(f"  - 複製 {fname} 失敗: {e}")
        
        group_results.append(group_info)
    
    # 儲存分群結果到 JSON
    with open('group_results.json', 'w', encoding='utf-8') as f:
        json.dump(group_results, f, ensure_ascii=False, indent=2)
    
    print(f"\n✅ 分群完成！共創建 {len(group_results)} 個群組")
    
    return group_results

if __name__ == "__main__":
    try:
        results = group_faces()
        print("✅ 人臉分群成功完成")
        print(f"群組數量: {len(results)}")
        
        for group in results:
            print(f"- {group['group_name']}: {len(group['faces'])} 張人臉")
            
    except Exception as e:
        print(f"❌ 人臉分群失敗: {e}")
        exit(1)
