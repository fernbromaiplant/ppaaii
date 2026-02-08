<?php
/**
 * AI 植物醫生 v5.0 - 美國伺服器專用穩定版
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

// --- 基礎設定 ---
$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU='; 
$api_key = "AIzaSyA7xcFM_WHk2TXqWc4sC9YWs0MIbLZ2UKE"; // 如果還是不行，請換成在新專案產生的 Key

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            $replyToken = $event['replyToken'];
            $messageId = $event['message']['id'];

            // 1. 從 LINE 下載圖片
            $url = 'https://api-data.line.me/v2/bot/message/' . $messageId . '/content';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $imgData = curl_exec($ch);
            curl_close($ch);

            // 2. 呼叫 Gemini API (使用 v1beta 與標準模型名稱)
            $api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
            
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
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $res_arr = json_decode($response, true);
            curl_close($ch);
            
            // 3. 回傳與錯誤診斷
            if (isset($res_arr['candidates'][0]['content']['parts'][0]['text'])) {
                $replyText = $res_arr['candidates'][0]['content']['parts'][0]['text'];
            } else {
                $msg = $res_arr['error']['message'] ?? '未知錯誤';
                $status = $res_arr['error']['status'] ?? 'UNKNOWN';
                
                // 針對 NOT_FOUND 提供具體建議
                if ($status === "NOT_FOUND") {
                    $replyText = "❌ 錯誤：找不到模型。\n這通常是 API Key 權限問題，請在 Google AI Studio 建立一個「新專案 (New Project)」的 Key。";
                } else {
                    $replyText = "❌ 診斷失敗\n原因：$msg\n狀態：$status";
                }
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
    echo "<h1>Plant Doctor v5.0</h1>";
    echo "Server Region: " . ($_ENV['RENDER_REGION'] ?? 'Global') . "<br>";
    echo "Status: Active";
}
