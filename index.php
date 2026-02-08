<?php
/**
 * AI 植物醫生 v15.0 - 終極穩定精簡版
 * 功能：自動切換模型、極簡回覆、防休眠相容
 */

// --- 設定區 ---
$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU='; 
$api_key = "AIzaSyAWdeWRm6RvqcsgKsrD17sk1K1P6Es9bvA"; // 請貼入你剛才測試成功的金鑰

// 1. 接收 LINE 訊息
$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        // 只處理圖片訊息
        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            $replyToken = $event['replyToken'];
            $messageId = $event['message']['id'];

            // 2. 下載 LINE 圖片資料
            $img_url = 'https://api-data.line.me/v2/bot/message/' . $messageId . '/content';
            $ch = curl_init($img_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $imgData = curl_exec($ch);
            curl_close($ch);

            // 3. 設定極簡指令 (Prompt)
            $prompt = "你是一位植醫。請嚴格依格式回覆，禁廢話：\n🪴名稱：[中文名]\n🩺診斷：[一句話]\n💊處方：[條列2點動作]\n💧澆水：[一句話]";

            // 4. 定義嘗試模型順序 (依據你帳號的診斷結果)
            $models = ['gemini-2.5-flash', 'gemini-1.5-flash', 'gemini-1.5-flash-latest'];
            $replyText = "⚠️ 暫時無法辨識，請稍後再試。";

            foreach ($models as $model) {
                $api_url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . $api_key;
                
                $payload = [
                    "contents" => [["parts" => [
                        ["text" => $prompt],
                        ["inline_data" => ["mime_type" => "image/jpeg", "data" => base64_encode($imgData)]]
                    ]]],
                    "generationConfig" => [
                        "maxOutputTokens" => 150,
                        "temperature" => 0.1
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

                // 如果成功抓到文字就跳出循環
                if (isset($res_arr['candidates'][0]['content']['parts'][0]['text'])) {
                    $replyText = $res_arr['candidates'][0]['content']['parts'][0]['text'];
                    break;
                }
            }

            // 5. 回傳給 LINE 使用者
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
    // 讓 Cron-job 探測時回傳 200 OK
    http_response_code(200);
    echo "Plant Doctor is Online.";
}
