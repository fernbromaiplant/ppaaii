<?php
/**
 * AI 植物醫生 v12.0 - 備援保險版
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

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
            $url = 'https://api-data.line.me/v2/bot/message/' . $messageId . '/content';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $imgData = curl_exec($ch);
            curl_close($ch);

            // 2. 定義要嘗試的模型順序 (按你的清單排序)
            $models_to_try = ['gemini-2.5-flash', 'gemini-1.5-flash', 'gemini-1.5-flash-latest'];
            $finalText = "";

            foreach ($models_to_try as $model) {
                $api_url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . $api_key;
                
                $payload = [
                    "contents" => [["parts" => [
                        ["text" => "你是一位資深植物專家。第一行請寫出植物名，之後給予繁體中文的照顧建議。"],
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

                if (isset($res_arr['candidates'][0]['content']['parts'][0]['text'])) {
                    $finalText = $res_arr['candidates'][0]['content']['parts'][0]['text'];
                    break; // 成功了就跳出循環
                } else {
                    $finalText = "❌ 嘗試 $model 失敗: " . ($res_arr['error']['message'] ?? '未知錯誤');
                }
            }

            // 3. 回傳給 LINE
            $post_data = [
                'replyToken' => $replyToken,
                'messages' => [['type' => 'text', 'text' => $finalText]]
            ];
            $ch = curl_init('https://api.line.me/v2/bot/message/reply');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
