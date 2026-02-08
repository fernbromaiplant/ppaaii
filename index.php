<?php
/**
 * AI 植物醫生 v7.0 - Gemini 2.0 Flash 專用版
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

// --- 基礎設定 ---
$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU='; 
$api_key = "AIzaSyCmuifzTMFWD7jUK5tClL6Z0UfaDwwadF4"; 

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            $replyToken = $event['replyToken'];
            $messageId = $event['message']['id'];

            // 1. 從 LINE 下載圖片內容
            $url = 'https://api-data.line.me/v2/bot/message/' . $messageId . '/content';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $imgData = curl_exec($ch);
            curl_close($ch);

            // 2. 呼叫 Gemini 2.0 API (根據你帳號清單首位的正確模型)
            $api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=" . $api_key;
            
            $prompt = "你是一位資深植物病理學家。第一行請直接寫出植物名，之後請針對健康狀況與處方給予簡短建議（請使用繁體中文）。";

            $payload = [
                "contents" => [["parts" => [
                    ["text" => $prompt],
                    ["inline_data" => ["mime_type" => "image/jpeg", "data" => base64_encode($imgData)]]
                ]]]
            ];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 避免 SSL 憑證問題
            $response = curl_exec($ch);
            $res_arr = json_decode($response, true);
            curl_close($ch);
            
            // 3. 處理回傳結果
            if (isset($res_arr['candidates'][0]['content']['parts'][0]['text'])) {
                $replyText = $res_arr['candidates'][0]['content']['parts'][0]['text'];
            } else {
                // 如果失敗，輸出具體錯誤供診斷
                $msg = $res_arr['error']['message'] ?? '解析失敗';
                $replyText = "❌ 診斷發生問題：$msg";
            }

            // 4. 將結果回傳給 LINE 使用者
            $post_data = [
                'replyToken' => $replyToken,
                'messages' => [['type' => 'text', 'text' => $replyText]]
            ];
            $ch = curl_init('https://api.line.me/v2/bot/message/reply');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_exec($ch);
            curl_close($ch);
        }
    }
} else {
    // 網頁預覽診斷資訊
    echo "<h1>Plant Doctor 2.0 Online</h1>";
    echo "Target Model: gemini-2.0-flash-exp<br>";
    echo "Status: Waiting for LINE events...";
}
