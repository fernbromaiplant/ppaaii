<?php
/**
 * AI 植物醫生 v9.0 - 自動路徑修復版
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU='; 
// *** 請在這裡貼上你剛剛 Copy 的那把最新 API Key ***
$api_key = "AIzaSyAWdeWRm6RvqcsgKsrD17sk1K1P6Es9bvA"; 

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            $replyToken = $event['replyToken'];
            $messageId = $event['message']['id'];

            // 1. 下載 LINE 圖片
            $url = 'https://api-data.line.me/v2/bot/message/' . $messageId . '/content';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $imgData = curl_exec($ch);
            curl_close($ch);

            // 2. 呼叫 Gemini - 嘗試 v1beta (這是目前對 Flash 最穩的路徑)
            $api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
            
            $payload = [
                "contents" => [["parts" => [
                    ["text" => "你是一位植物專家。請用繁體中文告訴我這是什麼植物，以及如何照顧牠。"],
                    ["inline_data" => ["mime_type" => "image/jpeg", "data" => base64_encode($imgData)]]
                ]]]
            ];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $res_arr = json_decode($response, true);
            curl_close($ch);
            
            // 3. 判斷回傳結果
            if (isset($res_arr['candidates'][0]['content']['parts'][0]['text'])) {
                $replyText = $res_arr['candidates'][0]['content']['parts'][0]['text'];
            } else {
                // 如果還是失敗，把錯誤碼噴出來，我們直接看看到底是哪邊不對
                $error_msg = $res_arr['error']['message'] ?? '未知錯誤';
                $replyText = "⚠️ 呼叫失敗：\n$error_msg\n\n請確認此 Key 是否已在 AI Studio 啟用。";
            }

            // 4. 回傳給 LINE
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
    echo "Bot is running. Waiting for images...";
}
