<?php
/**
 * AI 植物醫生 v2026 - 穩定部署版
 * 1. 支援 Render 環境變數 (GEMINI_API_KEY)
 * 2. 採用 Google v1 正式版 API
 * 3. 鎖定 gemini-2.0-flash 穩定模型
 */

// --- 設定區 ---
$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU=';
$api_key = getenv('GEMINI_API_KEY'); 

// --- 接收 LINE 訊息 ---
$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            $replyToken = $event['replyToken'];
            $messageId = $event['message']['id'];

            // 1. 下載 LINE 圖片內容
            $img_url = 'https://api-data.line.me/v2/bot/message/' . $messageId . '/content';
            $ch = curl_init($img_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $imgData = curl_exec($ch);
            curl_close($ch);

            // 2. 準備 AI 診斷請求
            $prompt = "你是一位專業植物醫生。請依格式回覆：\n🪴 植物名稱：[中文名]\n🩺 健康診斷：[說明現況]\n💊 照護建議：[具體行動]\n💧 澆水指南：[頻率]";
            
            // 使用 2026 年最穩定的 v1 路徑與 2.0 模型
            $api_url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=" . $api_key;

            $payload = [
                "contents" => [["parts" => [
                    ["text" => $prompt],
                    ["inline_data" => ["mime_type" => "image/jpeg", "data" => base64_encode($imgData)]]
                ]]],
                "generationConfig" => [
                    "maxOutputTokens" => 800,
                    "temperature" => 0.
