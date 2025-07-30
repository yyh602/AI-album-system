# heic2jpg.py
import sys
import subprocess
import os

def convert_heic_to_jpg(input_path, output_path):
    try:
        result = subprocess.run(["magick", input_path, output_path], capture_output=True, text=True)
        if result.returncode != 0:
            print(f"ImageMagick 轉檔失敗: {result.stderr}")
            sys.exit(1)
        print("轉檔成功")
    except Exception as e:
        print(f"轉檔過程出錯: {e}")
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("用法: python heic2jpg.py <輸入路徑> <輸出路徑>")
        sys.exit(1)

    convert_heic_to_jpg(sys.argv[1], sys.argv[2])
