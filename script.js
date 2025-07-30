function fetchWeather(lat, lon) {
    let apiKey = 'e693f46a41884ca4bed44714252603';  // 註冊 WeatherAPI 獲取
    let url = `https://api.weatherapi.com/v1/current.json?key=${apiKey}&q=${lat},${lon}&lang=zh`;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById("weather").innerText = "天氣資訊讀取失敗";
                return;
            }
            let conditionText = data.current.condition.text;
            let conditionIcon = data.current.condition.icon;

            document.getElementById("weather").innerText = `天氣：${conditionText}`;
            document.getElementById("weather-icon").src = `https:${conditionIcon}`;
            document.getElementById("weather-icon").style.display = 'inline';
        })
        .catch(error => {
            console.error("天氣 API 錯誤:", error);
            document.getElementById("weather").innerText = "天氣資訊讀取失敗";
        });
}

