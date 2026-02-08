<?php
/**
 * AI 植物醫生 - 模型清單診斷工具
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU='; 
$api_key = "AIzaSyCmuifzTMFWD7jUK5tClL6Z0UfaDwwadF4"; // 請確保這是你新帳號的 Key

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        $replyToken = $event['replyToken'];

        // 直接跟 Google 要模型清單
        $check_url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $api_key;
        
        $ch = curl_init($check_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        $data = json_decode($res, true);
        curl_close($ch);

        $model_names = [];
        if (isset($data['models'])) {
            foreach ($data['models'] as $m) {
                // 只抓取包含 flash 或 pro 的關鍵模型
                if (strpos($m['name'], 'gemini') !== false) {
                    $model_names[] = str_replace('models/', '', $m['name']);
                }
            }
            $replyText = "✅ 你的 Key 支援的模型有：\n" . implode("\n", $model_names);
        } else {
            $replyText = "❌ 無法取得清單。原因：" . ($data['error']['message'] ?? '未知錯誤');
        }

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
