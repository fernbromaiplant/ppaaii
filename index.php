<?php
/**
 * AI 植物醫生 v16.0 - 平衡進化版
 */

$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU='; 
$api_key = "AIzaSyAWdeWRm6RvqcsgKsrD17sk1K1P6Es9bvA"; 

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            $replyToken = $event['replyToken'];
            $messageId = $event['message']['id'];

            // 1. 下載圖片
            $img_url = 'https://api-data.line.me/v2/bot/message/' . $messageId . '/content';
            $ch = curl_init($img_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $imgData = curl_exec($ch);
            curl_close($ch);

            // 2. 稍微增加細節的指令 (Prompt)
            $prompt = "你是一位專業植物醫生。請依格式回覆：
🪴 植物名稱：[中文名] (英文名)
🩺 健康診斷：[詳細說明植物目前的生長狀況與問題]
💊 照護建議：[提供3點具體的改善行動]
💧 澆水指南：[說明適合的澆水頻率與方式]";

            // 3. 模型嘗試
            $models = ['gemini-2.5-flash', 'gemini-1.5-flash'];
            $replyText = "";
            $last_error = "";

            foreach ($models as $model) {
                $api_url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . $api_key;
                
                $payload = [
                    "contents" => [["parts" => [
                        ["text" => $prompt],
                        ["inline_data" => ["mime_type" => "image/jpeg", "data" => base64_encode($imgData)]]
                    ]]],
                    "generationConfig" => [
                        "maxOutputTokens" => 500, // 調高字數上限
                        "temperature" => 0.7      // 稍微提高溫度，讓說話自然一點
                    ]
                ];

                $ch = curl_init($api_url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $res = curl_exec($ch);
                $res_arr = json_decode($res, true);
                curl_close($ch);

                if (isset($res_arr['candidates'][0]['content']['parts'][0]['text'])) {
                    $replyText = $res_arr['candidates'][0]['content']['parts'][0]['text'];
                    break;
                } else {
                    $last_error = $res_arr['error']['message'] ?? '未知錯誤';
                }
            }

            // 如果全部失敗，顯示具體錯誤
            if (empty($replyText)) {
                $replyText = "⚠️ 辨識失敗，原因：\n" . $last_error . "\n\n建議：請稍等一分鐘再試，或確認圖片是否清晰。";
            }

            // 4. 回傳
            $post_data = [
                'replyToken' => $replyToken,
                'messages' => [['type' => 'text', 'text' => trim($replyText)]]
            ];
            $ch = curl_init('https://api.line.me/v2/bot/message/reply');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
