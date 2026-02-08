<?php
/**
 * AI 植物醫生 - 終極錯誤追蹤版
 */
// 強制顯示所有錯誤，不要空白
error_reporting(E_ALL);
ini_set('display_errors', 1);

$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU='; 
$api_key = "AIzaSyCmuifzTMFWD7jUK5tClL6Z0UfaDwwadF4"; 

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        $replyToken = $event['replyToken'];

        // 測試 1: 嘗試連線 Google
        $check_url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $api_key;
        
        $ch = curl_init($check_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        $curl_error = curl_error($ch); // 抓取連線層面的錯誤
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $replyText = "--- 診斷報告 ---\n";
        $replyText .= "HTTP 狀態碼: $http_code\n";
        
        if ($curl_error) {
            $replyText .= "連線錯誤: $curl_error\n";
        }

        $data = json_decode($res, true);
        if (isset($data['models'])) {
            $replyText .= "✅ 成功取得清單！\n";
            $replyText .= "首個可用模型: " . $data['models'][0]['name'];
        } else {
            $replyText .= "❌ API 報錯內容: " . json_encode($data);
        }

        // 回傳給 LINE
        $post_data = [
            'replyToken' => $replyToken,
            'messages' => [['type' => 'text', 'text' => mb_strcut($replyText, 0, 1000)]]
        ];
        
        $ch = curl_init('https://api.line.me/v2/bot/message/reply');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
} else {
    echo "Webview 測試: 如果你看到這行，代表伺服器活著。";
}
