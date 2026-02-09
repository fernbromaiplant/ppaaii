<?php
/**
 * AI 植物醫生 v2026.1 - 終極穩定版
 * 特色：環境變數隱藏、多模型自動回退、v1/v1beta 雙路徑測試
 */

// 1. 基本設定
$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU=';
$api_key = getenv('GEMINI_API_KEY');

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            $replyToken = $event['replyToken'];
            $messageId = $event['message']['id'];

            // 2. 下載 LINE 圖片
            $ch = curl_init('https://api-data.line.me/v2/bot/message/' . $messageId . '/content');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $imgData = curl_exec($ch);
            curl_close($ch);

            // 3. AI 提示詞
            $prompt = "你是一位專業植物醫生。請依格式回覆：\n🪴 植物名稱：[中文名] (英文名)\n🩺 健康診斷：[說明目前狀況]\n💊 照護建議：[提供2點具體行動]\n💧 澆水指南：[說明頻率]";

            // 4. 2026 年最強穩定模型清單
            $models = ['gemini-3-flash', 'gemini-2.0-flash', 'gemini-1.5-flash'];
            $api_versions = ['v1', 'v1beta'];
            
            $replyText = "";
            $last_error = "未找到可用模型";

            if (empty($api_key)) {
                $replyText = "❌ 系統錯誤：Render 環境變數 GEMINI_API_KEY 未設定或抓取不到。";
            } else {
                // 雙重迴圈嘗試：版本 x 模型
                foreach ($api_versions as $ver) {
                    foreach ($models as $model) {
                        $api_url = "https://generativelanguage.googleapis.com/{$ver}/models/{$model}:generateContent?key=" . $api_key;
                        
                        $payload = [
                            "contents" => [["parts" => [
                                ["text" => $prompt],
                                ["inline_data" => ["mime_type" => "image/jpeg", "data" => base64_encode($imgData)]]
                            ]]],
                            "generationConfig" => ["maxOutputTokens" => 500, "temperature" => 0.7]
                        ];

                        $ch = curl_init($api_url);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        $res = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $res_arr = json_decode($res, true);
                        curl_close($ch);

                        if ($http_code == 200 && isset($res_arr['candidates'][0]['content']['parts'][0]['text'])) {
                            $replyText = $res_arr['candidates'][0]['content']['parts'][0]['text'];
                            break 2; // 成功即跳出兩層迴圈
                        } else {
                            $last_error = "版本 $ver 模型 $model 失敗 (HTTP $http_code)";
                            if (isset($res_arr['error']['message'])) $last_error .= ": " . $res_arr['error']['message'];
                        }
                    }
                }
            }

            // 5. 回傳訊息給 LINE
            $finalMessage = empty($replyText) ? "❌ 診斷失敗\n原因：$last_error\n💡 請確認 API Key 是否在 Render 後台正確設定。" : trim($replyText) . "\n\n🌿 更多資訊請見【蕨積】：\nhttps://fernbrom.byethost24.com";

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
