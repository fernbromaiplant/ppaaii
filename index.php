<?php
/**
 * AI 植物醫生 v18.0 - 帶連結增強版
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

            // 2. 指令
            $prompt = "你是一位專業植物醫生。請依格式回覆，禁廢話：\n🪴 植物名稱：[中文名] (英文名)\n🩺 健康診斷：[說明目前生長狀況與問題]\n💊 照護建議：[提供2-3點具體行動]\n💧 澆水指南：[說明適合的頻率]";

            // 3. 嘗試邏輯
            $models = ['gemini-2.5-flash', 'gemini-1.5-flash'];
            $replyText = "";
            $last_error = "";

            foreach ($models as $model) {
                for ($attempt = 1; $attempt <= 2; $attempt++) {
                    $api_url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . $api_key;
                    $payload = [
                        "contents" => [["parts" => [
                            ["text" => $prompt],
                            ["inline_data" => ["mime_type" => "image/jpeg", "data" => base64_encode($imgData)]]
                        ]]],
                        "generationConfig" => ["maxOutputTokens" => 400, "temperature" => 0.5]
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
                        break 2;
                    } else {
                        $last_error = $res_arr['error']['message'] ?? '系統繁忙';
                        if ($attempt < 2) sleep(2);
                    }
                }
            }

            // 4. 組合最終訊息 (加上你的網站連結)
            if (empty($replyText)) {
                $finalMessage = "❌ 診斷失敗\n原因：$last_error\n\n💡 建議：稍等一分鐘再試。";
            } else {
                $finalMessage = trim($replyText) . "\n\n🌿 更多資訊請見【蕨積】：\nhttps://fernbrom.byethost24.com";
            }

            // 5. 回傳
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
