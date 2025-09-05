#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
使用 insightface 的人臉分群腳本 - 類別包裝修正版
使用類別包裝避免函數替換問題
支援特徵向量資料庫儲存
"""

import sys
sys.stdout.reconfigure(encoding='utf-8')

import os
import numpy as np
from sklearn.metrics.pairwise import cosine_similarity
import json
import shutil
import mysql.connector
from mysql.connector import Error

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

# 資料庫連接設定
DB_CONFIG = {
    'host': 'album.mysql.database.azure.com',
    'user': 's1411131020',
    'password': 'Aa123456',
    'database': 'album',
    'port': 3306,
    'charset': 'utf8mb4'
}

def get_database_connection():
    """獲取資料庫連接"""
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        if connection.is_connected():
            print("✅ 資料庫連接成功")
            return connection
    except Error as e:
        print(f"❌ 資料庫連接失敗: {e}")
        return None
    return None

def ensure_feature_vector_column(connection):
    """確保 faces 表存在 feature_vector 欄位"""
    try:
        cursor = connection.cursor()
        
        # 檢查欄位是否存在
        cursor.execute("SHOW COLUMNS FROM faces LIKE 'feature_vector'")
        result = cursor.fetchone()
        
        if not result:
            # 欄位不存在，新增它
            cursor.execute("ALTER TABLE faces ADD COLUMN feature_vector TEXT")
            connection.commit()
            print("✅ 已新增 feature_vector 欄位到 faces 表")
        else:
            print("✅ feature_vector 欄位已存在")
            
        cursor.close()
        return True
        
    except Error as e:
        print(f"❌ 確保 feature_vector 欄位失敗: {e}")
        return False

def save_feature_vector_to_database(connection, face_filename, feature_vector, confidence=0.8):
    """將特徵向量儲存到資料庫"""
    try:
        cursor = connection.cursor()
        
        # 檢查人臉是否已經存在
        check_sql = "SELECT id FROM faces WHERE face_filename = %s"
        cursor.execute(check_sql, (face_filename,))
        result = cursor.fetchone()
        
        if result:
            # 人臉已存在，更新特徵向量
            update_sql = """
                UPDATE faces 
                SET feature_vector = %s, confidence = %s, updated_at = NOW() 
                WHERE face_filename = %s
            """
            cursor.execute(update_sql, (json.dumps(feature_vector.tolist()), confidence, face_filename))
            print(f"✅ 已更新 {face_filename} 的特徵向量")
        else:
            # 人臉不存在，插入新記錄
            insert_sql = """
                INSERT INTO faces (photo_id, face_filename, confidence, bounding_box, 
                                 face_size, margin_used, crop_dimensions, original_image, 
                                 feature_vector, created_at, updated_at) 
                VALUES (1, %s, %s, '[]', 'medium', 8, '80x80', '', %s, NOW(), NOW())
            """
            feature_json = json.dumps(feature_vector.tolist())
            cursor.execute(insert_sql, (face_filename, confidence, feature_json))
            print(f"✅ 已儲存 {face_filename} 的特徵向量到資料庫")
        
        connection.commit()
        cursor.close()
        return True
        
    except Error as e:
        print(f"❌ 儲存特徵向量到資料庫失敗: {e}")
        return False

def batch_save_feature_vectors(connection, face_paths, embeddings):
    """批量儲存特徵向量到資料庫"""
    if not connection:
        print("❌ 資料庫連接不可用，跳過特徵向量儲存")
        return False
    
    # 確保 feature_vector 欄位存在
    if not ensure_feature_vector_column(connection):
        print("❌ 無法確保 feature_vector 欄位，跳過特徵向量儲存")
        return False
    
    print(f"\n💾 開始批量儲存特徵向量到資料庫...")
    saved_count = 0
    total_count = len(face_paths)
    
    for i, (face_path, embedding) in enumerate(zip(face_paths, embeddings)):
        face_filename = os.path.basename(face_path)
        
        if save_feature_vector_to_database(connection, face_filename, embedding):
            saved_count += 1
        
        # 顯示進度
        if (i + 1) % 10 == 0 or (i + 1) == total_count:
            print(f"進度: {i + 1}/{total_count} ({((i + 1)/total_count)*100:.1f}%)")
    
    print(f"✅ 特徵向量儲存完成: {saved_count}/{total_count}")
    return saved_count == total_count

def extract_features_with_insightface(img_path):
    """使用 insightface 提取特徵（類別包裝修正版）"""
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

def get_next_group_number(group_dir):
    """獲取下一個可用的群組編號，避免覆蓋現有資料夾"""
    if not os.path.exists(group_dir):
        return 1
    
    existing_groups = []
    for item in os.listdir(group_dir):
        if os.path.isdir(os.path.join(group_dir, item)) and item.startswith('people_'):
            try:
                # 提取數字部分
                number = int(item.split('_')[1])
                existing_groups.append(number)
            except (ValueError, IndexError):
                continue
    
    if not existing_groups:
        return 1
    
    return max(existing_groups) + 1

def group_faces():
    """主要的人臉分群函數"""
    
    # 設定路徑
    face_dir = "faces"
    group_dir = "group"
    
    # 確保 group 目錄存在（不清理現有資料）
    os.makedirs(group_dir, exist_ok=True)
    
    # 獲取下一個可用的群組編號
    next_group_number = get_next_group_number(group_dir)
    print(f"📁 下一個群組編號將從 {next_group_number} 開始")
    
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
                
                if w < 80 or h < 80:  # 適中的最小尺寸要求
                    print(f"圖片過小，跳過: {fname}")
                    continue
            else:
                # 如果 OpenCV 不可用，使用檔案大小檢查
                file_size = os.path.getsize(fpath)
                if file_size < 2000:  # 小於 2KB，適中的品質要求
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
            
            # 顯示特徵向量的詳細內容
            print(f"特徵向量擷取成功: {fname} (維度: {len(embedding)})")
            print(f"  完整特徵向量:")
            print(f"    {embedding}")
            print(f"  數值範圍: {np.min(embedding):.6f} ~ {np.max(embedding):.6f}")
            print(f"  平均值: {np.mean(embedding):.6f}")
            print(f"  標準差: {np.std(embedding):.6f}")
            print(f"  向量類型: {type(embedding)}")
            print(f"  數據類型: {embedding.dtype}")
            print("  " + "-" * 50)
            
            embeddings.append(embedding)
            face_paths.append(fpath)
            
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
    
    # 修正：將相似度轉換為距離矩陣，然後使用圖論分群
    threshold = 0.425  # 適中的分群：平衡準確度與速度
    
    # 將相似度轉換為距離：distance = 1 - similarity
    # 這樣確保所有值都是非負的，且相似度越高，距離越小
    distance_matrix = 1 - sim_matrix
    
    # 使用圖論分群（基於距離）
    n = len(distance_matrix)
    adj = [[] for _ in range(n)]
    
    for i in range(n):
        for j in range(i + 1, n):
            # 距離小於閾值表示相似度高
            if distance_matrix[i][j] <= (1 - threshold):
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
        group_name = f"people_{next_group_number + idx}"
        group_path = os.path.join(group_dir, group_name)
        os.makedirs(group_path, exist_ok=True)
        
        group_info = {
            'group_name': group_name,
            'faces': []
        }
        
        print(f"群組 {group_name} ：{len(group)} 張臉")
        
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
    
    # 嘗試連接資料庫並儲存特徵向量
    print("\n" + "="*60)
    print("💾 特徵向量資料庫儲存")
    print("="*60)
    
    db_connection = get_database_connection()
    if db_connection:
        try:
            # 批量儲存特徵向量到資料庫
            db_success = batch_save_feature_vectors(db_connection, face_paths, embeddings)
            if db_success:
                print("🎉 所有特徵向量已成功儲存到資料庫！")
            else:
                print("⚠️ 部分特徵向量儲存失敗")
        except Exception as e:
            print(f"❌ 資料庫儲存過程中發生錯誤: {e}")
        finally:
            db_connection.close()
            print("✅ 資料庫連接已關閉")
    else:
        print("⚠️ 無法連接資料庫，特徵向量將不會儲存到資料庫")
    
    # 分析特徵向量的統計資訊
    analyze_feature_vectors(embeddings, face_paths)
    
    print(f"\n完成，共分 {len(groups)} 群")
    
    return group_results

def analyze_feature_vectors(embeddings, face_paths):
    """分析特徵向量的統計資訊"""
    if not embeddings:
        return
        
    print("\n" + "="*60)
    print("🔍 特徵向量詳細分析")
    print("="*60)
    
    # 轉換為 numpy 陣列
    X = np.array(embeddings)
    
    print(f"總特徵向量數量: {len(embeddings)}")
    print(f"每個向量維度: {X.shape[1]}")
    print(f"數據類型: {X.dtype}")
    
    # 統計資訊
    print(f"\n📊 數值統計:")
    print(f"  全域最小值: {np.min(X):.6f}")
    print(f"  全域最大值: {np.max(X):.6f}")
    print(f"  全域平均值: {np.mean(X):.6f}")
    print(f"  全域標準差: {np.std(X):.6f}")
    
    # 每個向量的統計
    print(f"\n📈 個別向量統計:")
    for i, (embedding, face_path) in enumerate(zip(embeddings, face_paths)):
        fname = os.path.basename(face_path)
        print(f"  {fname}:")
        print(f"    最小值: {np.min(embedding):.6f}")
        print(f"    最大值: {np.max(embedding):.6f}")
        print(f"    平均值: {np.mean(embedding):.6f}")
        print(f"    標準差: {np.std(embedding):.6f}")
        print(f"    完整特徵向量:")
        print(f"      {embedding}")
        print()
    
    # 相似度分析
    print(f"\n🔗 相似度分析:")
    if len(embeddings) > 1:
        sim_matrix = cosine_similarity(X)
        print(f"  平均相似度: {np.mean(sim_matrix):.6f}")
        print(f"  相似度標準差: {np.std(sim_matrix):.6f}")
        print(f"  最高相似度: {np.max(sim_matrix[sim_matrix < 1.0]):.6f}")
        print(f"  最低相似度: {np.min(sim_matrix):.6f}")
    
    print("="*60)

def extract_single_face_features(image_path):
    """提取單張圖片的人臉特徵向量，返回 JSON 格式（用於 PHP 調用）"""
    try:
        if not OPENCV_AVAILABLE:
            return json.dumps({'error': 'OpenCV 不可用'})
        
        if app is None:
            return json.dumps({'error': 'InsightFace 應用程式未初始化'})
        
        # 載入圖片
        img = cv2.imread(image_path)
        if img is None:
            return json.dumps({'error': f'無法載入圖片: {image_path}'})
        
        # 使用 app.get() 提取特徵
        faces = app.get(img)
        
        if not faces:
            return json.dumps({'embeddings': [], 'message': '沒有偵測到人臉'})
        
        # 提取所有偵測到的人臉特徵向量
        embeddings = []
        for i, face in enumerate(faces):
            if face.embedding is not None:
                # 將 numpy 陣列轉換為 Python 列表，以便 JSON 序列化
                embedding_list = face.embedding.tolist()
                embeddings.append({
                    'face_index': i,
                    'embedding': embedding_list,
                    'dimension': len(embedding_list),
                    'confidence': getattr(face, 'det_score', 0.0)
                })
        
        result = {
            'success': True,
            'image_path': image_path,
            'faces_detected': len(faces),
            'embeddings': embeddings
        }
        
        return json.dumps(result, ensure_ascii=False, indent=2)
        
    except Exception as e:
        return json.dumps({'error': f'特徵提取失敗: {str(e)}'})

# 全域變數
app = None

if __name__ == "__main__":
    try:
        print("=== 人臉分群系統啟動（類別包裝修正版 + 資料庫儲存）===")
        print(f"OpenCV 可用: {OPENCV_AVAILABLE}")
        print(f"insightface 可用: {USE_INSIGHTFACE}")
        
        # 測試資料庫連接
        print("\n🔌 測試資料庫連接...")
        test_connection = get_database_connection()
        if test_connection:
            print("✅ 資料庫連接測試成功")
            test_connection.close()
        else:
            print("⚠️ 資料庫連接測試失敗，特徵向量將不會儲存到資料庫")
        
        # 初始化 insightface 模型（類別包裝修正版）
        if USE_INSIGHTFACE and OPENCV_AVAILABLE:
            print("\n🤖 載入 ArcFace 模型中...")
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
                        
                        # 類別包裝修正版：使用修正的 InferenceSession 類別
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
        print("\n🚀 開始執行人臉分群...")
        results = group_faces()
        print("✅ 人臉分群成功完成")
        print(f"群組數量: {len(results)}")
        
        for group in results:
            print(f"- {group['group_name']}: {len(group['faces'])} 張人臉")
            
        print("\n🎉 人臉分群和特徵向量儲存全部完成！")
            
    except Exception as e:
        print(f"❌ 人臉分群失敗: {e}")
        exit(1)
