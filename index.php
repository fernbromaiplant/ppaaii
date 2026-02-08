<?php
/**
 * 植物醫生 - 徹底排除版 (v10.0)
 */
ini_set('display_errors', 0);
$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU=';
$api_key = "AIzaSyAWdeWRm6RvqcsgKsrD17sk1K1P6Es9bvA"; 

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        $replyToken = $event['replyToken'];
        
        // 動作：直接問 Google 你家有哪些模型可以用
        $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $api_key;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        $data = json_decode($res, true);
        curl_close($ch);

        if (isset($data['models'])) {
            // 如果有清單，回傳前兩個模型名字
            $m1 = $data['models'][0]['name'] ?? '無';
            $m2 = $data['models'][1]['name'] ?? '無';
            $replyText = "✅ Key 活著！\n可用模型 1: $m1\n可用模型 2: $m2";
        } else {
            // 如果連清單都沒有，直接看 Google 噴什麼髒話
            $err = $data['error']['message'] ?? '完全沒回應';
            $replyText = "❌ Key 還是廢的！\n原因：$err";
        }

        $post_data = ['replyToken' => $replyToken, 'messages' => [['type' => 'text', 'text' => $replyText]]];
        $ch = curl_init('https://api.line.me/v2/bot/message/reply');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_exec($ch);
    }
}
