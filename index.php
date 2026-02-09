<?php
/**
 * AI 植物醫生 v2026 - 穩定部署版
 * 1. 支援 Render 環境變數 (GEMINI_API_KEY)
 * 2. 採用 Google v1 正式版 API
 * 3. 鎖定 gemini-2.0-flash 穩定模型
 */

// --- 設定區 ---
$access_token = 'Fkl3e1u1smWN7MSqd6kVn/1J3H/6zVyNnFadGTjkbJt6yHRfNP1HbvFatK/K7o7S9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw5lrjIGu+ZQwCWGnartaMj2n1Sh12sKUBukN7nSb4FhhQdB04t89/1O/w1cDnyilFU=';
$api_key = getenv('GEMINI_API_KEY'); 

// --- 接收 LINE 訊息 ---
$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            $replyToken = $event['replyToken'];
            $messageId = $event['message']['id'];

            // 1. 下載 LINE 圖片內容
            $img_url = 'https://api-data.line.me/v2/bot/message/' . $messageId . '/content';
            $ch = curl_init($img_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $imgData = curl_exec($ch);
            curl_close($ch);

            // 2. 準備 AI 診斷請求
            $prompt = "你是一位專業植物醫生。請依格式回覆：\n🪴 植物名稱：[中文名]\n🩺 健康診斷：[說明現況]\n💊 照護建議：[具體行動]\n💧 澆水指南：[頻率]";
            
            // 使用 2026 年最穩定的 v1 路徑與 2.0 模型
            $api_url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=" . $api_key;

            $payload = [
                "contents" => [["parts" => [
                    ["text" => $prompt],
                    ["inline_data" => ["mime_type" => "image/jpeg", "data" => base64_encode($imgData)]]
                ]]],
                "generationConfig" => [
                    "maxOutputTokens" => 800,
                    "temperature" => 0.7
                ]
            ];

            // 3. 呼叫 Gemini API
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $res = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $res_arr = json_decode($res, true);
            curl_close($ch);

            // 4. 解析 AI 回應
            $replyText = "";
            if ($http_code == 200 && isset($res_arr['candidates'][0]['content']['parts'][0]['text'])) {
                $replyText = $res_arr['candidates'][0]['content']['parts'][0]['text'];
            } else {
                $error_detail = $res_arr['error']['message'] ?? '連線逾時或模型維護中';
                $replyText = "❌ 診斷失敗 (HTTP $http_code)\n原因：$error_detail\n💡 建議：請確認 Render 後台 GEMINI_API_KEY 是否填寫正確。";
            }

            // 5. 回傳結果給 LINE 使用者
            $finalMessage = trim($replyText) . "\n\n🌿 更多資訊請見【蕨積】：\nhttps://fernbrom.byethost24.com";

            $post_data = [
                'replyToken' => $replyToken,
                'messages' => [['type' => 'text', 'text' => $finalMessage]]
            ];
            
            $ch = curl_init('https://api.line.me/v2/bot/message/reply');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
