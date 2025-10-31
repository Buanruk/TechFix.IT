<?php
/* line_push.php */
define('LINE_CHANNEL_ACCESS_TOKEN', '6PPEuPrvNfNYozuFtj7IRjpo/kNq26avxAXzMFBTBodJw9mCmpQNJ7v08B95yMzEUO2swglp56rkKNt1zU5Ec09stF4SZ4cqqnxWHRep6ER/PTWOXIjAksbOA0BmlXhMXWD0pYG10w8maeAOYjRmYwdB04t89/1O/w1cDnyilFU='); // <-- ใส่ของคุณ

/**
 * ส่ง Push Message ไปหา user หนึ่งคน
 * @return array [$httpStatus, $response, $curlErr]
 */
function line_push($toUserId, $text) {
    if (!$toUserId || !$text) return [0, null, 'missing param'];

    $url = 'https://api.line.me/v2/bot/message/push';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN
    ];
    $payload = json_encode([
        'to' => $toUserId,
        'messages' => [[
            'type' => 'text',
            'text' => $text
        ]]
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);
    $res  = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    return [$http, $res, $err];
}
