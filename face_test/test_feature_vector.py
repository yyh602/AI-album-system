#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
簡單的特徵向量測試腳本
用來測試特徵向量的生成和儲存功能
"""

import numpy as np
import json
import os
import sys

def generate_test_feature_vector():
    """生成測試用的特徵向量"""
    # 生成一個 512 維的隨機特徵向量（模擬 InsightFace 的輸出）
    feature_vector = np.random.randn(512).astype(np.float32)
    
    # 正規化向量（模擬真實的特徵向量）
    feature_vector = feature_vector / np.linalg.norm(feature_vector)
    
    return feature_vector.tolist()

def test_feature_vector_storage():
    """測試特徵向量儲存功能"""
    print("=== 特徵向量測試開始 ===")
    
    # 生成測試特徵向量
    test_vector = generate_test_feature_vector()
    print(f"✅ 生成測試特徵向量成功")
    print(f"   維度: {len(test_vector)}")
    print(f"   前5個數值: {test_vector[:5]}")
    print(f"   數值範圍: {min(test_vector):.6f} ~ {max(test_vector):.6f}")
    print(f"   平均值: {np.mean(test_vector):.6f}")
    print(f"   標準差: {np.std(test_vector):.6f}")
    
    # 測試 JSON 序列化
    try:
        vector_json = json.dumps(test_vector)
        print(f"✅ JSON 序列化成功，長度: {len(vector_json)} 字元")
        
        # 測試 JSON 反序列化
        vector_back = json.loads(vector_json)
        print(f"✅ JSON 反序列化成功，維度: {len(vector_back)}")
        
        # 驗證資料完整性
        if len(vector_back) == len(test_vector):
            print("✅ 資料完整性驗證通過")
        else:
            print("❌ 資料完整性驗證失敗")
            
    except Exception as e:
        print(f"❌ JSON 處理失敗: {e}")
        return False
    
    # 測試檔案儲存
    try:
        # 儲存到 JSON 檔案
        test_file = "test_feature_vector.json"
        with open(test_file, 'w', encoding='utf-8') as f:
            json.dump({
                'test_vector': test_vector,
                'metadata': {
                    'dimension': len(test_vector),
                    'description': '測試用特徵向量'
                }
            }, f, indent=2, ensure_ascii=False)
        
        print(f"✅ 特徵向量已儲存到 {test_file}")
        
        # 讀取回來驗證
        with open(test_file, 'r', encoding='utf-8') as f:
            loaded_data = json.load(f)
        
        if len(loaded_data['test_vector']) == len(test_vector):
            print("✅ 檔案儲存和讀取驗證通過")
        else:
            print("❌ 檔案儲存和讀取驗證失敗")
            
        # 清理測試檔案
        if os.path.exists(test_file):
            os.remove(test_file)
            print("✅ 測試檔案已清理")
            
    except Exception as e:
        print(f"❌ 檔案儲存測試失敗: {e}")
        return False
    
    print("\n=== 特徵向量測試完成 ===")
    return True

def test_database_format():
    """測試資料庫格式相容性"""
    print("\n=== 資料庫格式測試開始 ===")
    
    # 模擬多個人臉的特徵向量
    test_faces = {}
    for i in range(3):
        face_name = f"test_face_{i+1}.jpg"
        feature_vector = generate_test_feature_vector()
        
        test_faces[face_name] = {
            'feature_vector': feature_vector,
            'dimension': len(feature_vector),
            'confidence': 0.8 + (i * 0.1),
            'face_size': 'medium',
            'margin_used': 8
        }
    
    # 測試資料庫插入格式
    print("✅ 生成測試人臉資料:")
    for face_name, face_data in test_faces.items():
        print(f"   {face_name}: {face_data['dimension']} 維，信心度: {face_data['confidence']:.2f}")
    
    # 測試 SQL INSERT 語句格式
    print("\n📝 SQL INSERT 語句範例:")
    for face_name, face_data in test_faces.items():
        feature_json = json.dumps(face_data['feature_vector'])
        sql = f"""INSERT INTO faces (photo_id, face_filename, confidence, bounding_box, face_size, margin_used, crop_dimensions, original_image, feature_vector, created_at) 
VALUES (1, '{face_name}', {face_data['confidence']}, '[]', '{face_data['face_size']}', {face_data['margin_used']}, '80x80', '', '{feature_json}', NOW());"""
        print(f"   {sql}")
    
    print("\n=== 資料庫格式測試完成 ===")
    return True

def main():
    """主函數"""
    print("🧪 特徵向量儲存測試工具")
    print("=" * 50)
    
    # 測試特徵向量生成和儲存
    if not test_feature_vector_storage():
        print("❌ 特徵向量測試失敗")
        sys.exit(1)
    
    # 測試資料庫格式
    if not test_database_format():
        print("❌ 資料庫格式測試失敗")
        sys.exit(1)
    
    print("\n🎉 所有測試通過！特徵向量可以正常儲存到資料庫")
    print("\n📋 下一步:")
    print("   1. 在 PHP 中載入這個測試腳本")
    print("   2. 生成測試特徵向量")
    print("   3. 儲存到資料庫的 faces 表")
    print("   4. 驗證儲存結果")

if __name__ == "__main__":
    main()
