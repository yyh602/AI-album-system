#!/bin/bash

# 啟用 Python 虛擬環境
source /opt/venv/bin/activate

# 安裝 Google API 依賴
pip install google-generativeai==0.3.2
pip install google-cloud-vision==3.4.4

# 安裝 OpenCV 和其他依賴
pip install opencv-python-headless==4.8.1.78
pip install numpy==1.24.3 Pillow==10.0.1

echo "Python dependencies installed successfully!" 