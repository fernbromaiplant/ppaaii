<?php
/**
 * AI 植物醫生 v2026.FINAL
 * 修正內容：強制使用 v1 正式版路徑、使用 -latest 模型別名
 */

$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU=';
$api_key = getenv('GEMINI_API_KEY');

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            $replyToken = $event['replyToken'];
            $messageId = $event['message']['id'];

            // 1. 下載 LINE 圖片
            $ch = curl_init('https://api-data.line.me/v2/bot/message/' . $messageId . '/content');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $imgData = curl_exec($ch);
            curl_close($ch);

            // ... (前面下載圖片的代碼不變)

// 2. 提示詞
$prompt = "你是一位專業植物醫生。請依格式回覆：\n🪴 植物名稱：[中文名]\n🩺 健康診斷：[說明現況]\n💊 照護建議：[具體行動]\n💧 澆水指南：[頻率]";

// 3. 【重要修正】改用 2.0 版本路徑與模型名稱
$api_url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=" . $api_key;

$payload = [
    "contents" => [["parts" => [
        ["text" => $prompt],
        ["inline_data" => ["mime_type" => "image/jpeg", "data" => base64_encode($imgData)]]
    ]]],
    "generationConfig" => ["maxOutputTokens" => 800, "temperature" => 0.7]
];

// ... (後面的 curl 送出與解析代碼不變)
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $res = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $res_arr = json_decode($res, true);
            curl_close($ch);

            // 4. 解析結果
            $replyText = "";
            if ($http_code == 200 && isset($res_arr['candidates'][0]['content']['parts'][0]['text'])) {
                $replyText = $res_arr['candidates'][0]['content']['parts'][0]['text'];
            } else {
                $error_msg = $res_arr['error']['message'] ?? '未知錯誤';
                $replyText = "❌ 診斷失敗 (代碼: $http_code)\n原因：$error_msg\n💡 請確認 Render 環境變數是否填入全新 API Key。";
            }

            // 5. 加上連結並回傳
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
