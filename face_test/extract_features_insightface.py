#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
使用 InsightFace 提取人臉特徵向量的腳本
使用 app.get() 方法來偵測人臉並提取特徵向量
"""

import sys
import json
import os
import numpy as np

# 嘗試載入必要的套件
try:
    import cv2
    print("✅ OpenCV 載入成功")
    OPENCV_AVAILABLE = True
except ImportError:
    print("❌ OpenCV 無法載入")
    OPENCV_AVAILABLE = False

try:
    import insightface
    print("✅ InsightFace 載入成功")
    INSIGHTFACE_AVAILABLE = True
except ImportError as e:
    print(f"❌ InsightFace 無法載入: {e}")
    INSIGHTFACE_AVAILABLE = False

# 全域變數
app = None

def initialize_insightface():
    """初始化 InsightFace 應用程式"""
    global app
    try:
        if not INSIGHTFACE_AVAILABLE:
            return False, "InsightFace 套件未安裝"
        
        if not OPENCV_AVAILABLE:
            return False, "OpenCV 套件未安裝"
        
        print("載入 InsightFace 應用程式中...")
        
        # 使用 'buffalo_l' 模型，這是一個強大且常用的模型
        # 包含了人臉偵測、對齊和特徵提取功能
        app = insightface.app.FaceAnalysis(name='buffalo_l')
        app.prepare(ctx_id=0, det_size=(640, 640))
        
        print("✅ InsightFace 應用程式載入成功！")
        print(f"  模型名稱: buffalo_l")
        print(f"  偵測尺寸: 640x640")
        print(f"  使用 GPU: 是 (ctx_id=0)")
        
        return True, "初始化成功"
        
    except Exception as e:
        error_msg = f"InsightFace 初始化失敗: {str(e)}"
        print(f"❌ {error_msg}")
        return False, error_msg

def extract_face_features(image_path):
    """從圖像中提取人臉特徵向量"""
    try:
        if not OPENCV_AVAILABLE:
            return {'error': 'OpenCV 不可用'}
        
        if app is None:
            return {'error': 'InsightFace 應用程式未初始化'}
        
        # 檢查檔案是否存在
        if not os.path.exists(image_path):
            return {'error': f'找不到檔案: {image_path}'}
        
        # 載入圖片
        img = cv2.imread(image_path)
        if img is None:
            return {'error': f'無法載入圖片: {image_path}'}
        
        h, w = img.shape[:2]
        print(f"圖片尺寸: {w}x{h}")
        
        # 使用 app.get() 方法偵測人臉並提取特徵
        # 這個方法會返回包含多種資訊的 Face 物件，其中就包含了特徵向量
        faces = app.get(img)
        
        if not faces:
            return {
                'success': True,
                'image_path': image_path,
                'faces_detected': 0,
                'embeddings': [],
                'message': '沒有偵測到人臉'
            }
        
        print(f"偵測到 {len(faces)} 個人臉")
        
        # 提取每個偵測到人臉的特徵向量
        embeddings = []
        for i, face in enumerate(faces):
            if hasattr(face, 'embedding') and face.embedding is not None:
                # face.embedding 就是人臉的特徵向量
                embedding = face.embedding
                
                # 確保特徵向量是一維的
                if len(embedding.shape) > 1:
                    embedding = embedding.flatten()
                
                # 將 numpy 陣列轉換為 Python 列表，以便 JSON 序列化
                embedding_list = embedding.tolist()
                
                # 獲取額外資訊
                confidence = getattr(face, 'det_score', 0.0)
                bbox = getattr(face, 'bbox', [])
                
                embeddings.append({
                    'face_index': i,
                    'embedding': embedding_list,
                    'dimension': len(embedding_list),
                    'confidence': float(confidence),
                    'bbox': bbox.tolist() if hasattr(bbox, 'tolist') else bbox
                })
                
                print(f"  人臉 {i+1}: 特徵向量維度 {len(embedding_list)}, 信心度 {confidence:.4f}")
        
        result = {
            'success': True,
            'image_path': image_path,
            'faces_detected': len(faces),
            'embeddings': embeddings,
            'total_embeddings': len(embeddings)
        }
        
        return result
        
    except Exception as e:
        error_msg = f'特徵提取失敗: {str(e)}'
        print(f"❌ {error_msg}")
        return {'error': error_msg}

def main():
    """主函數"""
    print("=== InsightFace 人臉特徵提取系統 ===")
    
    # 初始化 InsightFace
    success, message = initialize_insightface()
    if not success:
        print(f"初始化失敗: {message}")
        return
    
    # 檢查命令列參數
    if len(sys.argv) < 2:
        print("使用方法: python extract_features_insightface.py <圖片路徑>")
        print("範例: python extract_features_insightface.py faces/face_1.jpg")
        return
    
    image_path = sys.argv[1]
    
    # 提取特徵
    result = extract_face_features(image_path)
    
    # 輸出 JSON 格式結果
    json_result = json.dumps(result, ensure_ascii=False, indent=2)
    print("\n=== 特徵提取結果 ===")
    print(json_result)
    
    # 如果是從 PHP 調用，只輸出 JSON
    if len(sys.argv) > 2 and sys.argv[2] == '--json-only':
        print(json_result, end='')

if __name__ == "__main__":
    main()
