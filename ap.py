import os
from flask import Flask, request, jsonify
import google.generativeai as genai

app = Flask(__name__)

# --- 重要：設定你的 Gemini API 金鑰 ---
# 這是你從 Google AI Studio (https://aistudio.google.com/app/apikey) 獲取的金鑰。
# 請務必將 'YOUR_GEMINI_API_KEY' 替換為你的實際金鑰！
# 更安全的做法是設定為環境變數，但為了方便測試，可以直接貼在這裡。
GOOGLE_API_KEY = 'AIzaSyBZZhisvYRS6RJe6v8kpKzLcNS8lbzjOlU' 

if GOOGLE_API_KEY == 'AIzaSyBZZhisvYRS6RJe6v8kpKzLcNS8lbzjOlU' or not GOOGLE_API_KEY:
    print("警告：請將 app.py 中的 'AIzaSyBZZhisvYRS6RJe6v8kpKzLcNS8lbzjOlU' 替換為您的實際金鑰，或設定環境變數 GEMINI_API_KEY。")
    # 在生產環境中，你可能會選擇在金鑰未設定時直接退出
    # exit("API 金鑰未設定！服務無法啟動。") 

genai.configure(api_key=GOOGLE_API_KEY)

@app.route('/api/gemini/ask', methods=['POST'])
def ask_gemini():
    """
    這個 API 端點會接收來自 PHP 的請求，呼叫 Gemini API，
    並將 Gemini 的回應傳回給 PHP。
    """

    # 檢查請求中是否包含資料 (即使用者輸入)
    if not request.data:
        return jsonify({"error": "No message provided"}), 400

    # 預期 PHP 發送的是純文本，並將其解碼為 UTF-8
    user_prompt = request.data.decode('utf-8') 

    try:
        # 初始化 Gemini 模型
        model = genai.GenerativeModel('gemini-2.5-flash')

        # 呼叫 Gemini API 生成內容
        response = model.generate_content(user_prompt)

        # 檢查是否有候選回應 (即 Gemini 成功生成了文本)
        if response.candidates:
            # 返回 Gemini 生成的文本給 PHP
            return response.text, 200
        elif response.prompt_feedback:
            # 如果內容因安全過濾或其他原因被 Gemini 拒絕
            safety_ratings_info = []
            for sr in response.prompt_feedback.safety_ratings:
                # 提取安全類別和機率以提供詳細訊息
                category = str(sr.category).split('.')[-1]
                probability = str(sr.probability).split('.')[-1]
                safety_ratings_info.append(f"{category}: {probability}")

            error_message = f"內容被安全過濾或無有效回應。詳情: {', '.join(safety_ratings_info)}"
            return jsonify({"error": error_message}), 400
        else:
            # 其他未預期的 Gemini 回應情況
            return jsonify({"error": "Gemini 未返回有效回應，但也沒有安全過濾提示。"}), 500

    except Exception as e:
        # 捕獲其他所有可能的錯誤 (例如網路問題、API 金鑰錯誤、模型呼叫失敗等)
        print(f"呼叫 Gemini API 時發生錯誤: {e}") # 將錯誤印到控制台，方便除錯
        return jsonify({"error": f"內部伺服器錯誤: {str(e)}"}), 500

if __name__ == '__main__':
    # 運行 Flask 應用程式
    # host='0.0.0.0' 表示這個服務可以從任何 IP 地址訪問 (在開發環境中常用)
    # port=5000 是此服務將監聽的埠口，PHP 會連接到這裡
    # debug=True 會提供更詳細的錯誤信息和自動重載功能 (僅用於開發)
    # 警告: 在生產環境中，應使用更健壯的 WSGI 伺服器 (如 Gunicorn 或 uWSGI)
    app.run(host='0.0.0.0', port=5000, debug=True)