<?php
/**
 * AI 植物醫生 v17.0 - 終極穩定強化版
 * 具備：自動重試、模型備援、防休眠、錯誤回報機制
 */

// --- 基礎設定 ---
$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU='; 
$api_key = "AIzaSyAWdeWRm6RvqcsgKsrD17sk1K1P6Es9bvA"; 

// 1. 接收來自 LINE 的 Hook
$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        // 只處理圖片訊息
        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            $replyToken = $event['replyToken'];
            $messageId = $event['message']['id'];

            // 2. 下載 LINE 圖片
            $img_url = 'https://api-data.line.me/v2/bot/message/' . $messageId . '/content';
            $ch = curl_init($img_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $imgData = curl_exec($ch);
            curl_close($ch);

            // 3. 設定診斷指令 (Prompt)
            $prompt = "你是一位專業植物醫生。請依格式回覆，禁廢話：\n🪴 植物名稱：[中文名] (英文名)\n🩺 健康診斷：[說明目前生長狀況與問題]\n💊 照護建議：[提供2-3點具體行動]\n💧 澆水指南：[說明適合的頻率]";

            // 4. 定義嘗試邏輯
            $models = ['gemini-2.5-flash', 'gemini-1.5-flash'];
            $replyText = "";
            $last_error = "";

            foreach ($models as $model) {
                // 每個模型自動重試 2 次 (針對 Google API 偶爾的 Busy 狀態)
                for ($attempt = 1; $attempt <= 2; $attempt++) {
                    $api_url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . $api_key;
                    
                    $payload = [
                        "contents" => [["parts" => [
                            ["text" => $prompt],
                            ["inline_data" => ["mime_type" => "image/jpeg", "data" => base64_encode($imgData)]]
                        ]]],
                        "generationConfig" => [
                            "maxOutputTokens" => 400,
                            "temperature" => 0.5 
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
                        break 2; // 成功辨識，跳出所有循環
                    } else {
                        $last_error = $res_arr['error']['message'] ?? '系統繁忙';
                        if ($attempt < 2) sleep(2); // 失敗了先睡 2 秒再重試
                    }
                }
            }

            // 5. 處理最終結果
            if (empty($replyText)) {
                $replyText = "❌ 診斷失敗\n原因：$last_error\n\n💡 建議：\n1. 稍等一分鐘再試。\n2. 確保圖片清晰、光線充足。\n3. 若持續失敗，請檢查 API Key 權限。";
            }

            // 6. 回傳結果給使用者
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
} else {
    // 讓 Cron-job 檢查時顯示正常
    http_response_code(200);
    echo "Plant Doctor v17.0 is Online.";
}
