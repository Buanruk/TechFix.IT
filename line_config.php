<?php
// ใส่ Channel access token ของ LINE Messaging API
const LINE_CH_ACCESS_TOKEN = '6PPEuPrvNfNYozuFtj7IRjpo/kNq26avxAXzMFBTBodJw9mCmpQNJ7v08B95yMzEUO2swglp56rkKNt1zU5Ec09stF4SZ4cqqnxWHRep6ER/PTWOXIjAksbOA0BmlXhMXWD0pYG10w8maeAOYjRmYwdB04t89/1O/w1cDnyilFU=';

function line_push($to, array $messages){
  if (!$to || !LINE_CH_ACCESS_TOKEN) return false;
  $url = 'https://api.line.me/v2/bot/message/push';
  $payload = json_encode(['to'=>$to, 'messages'=>$messages], JSON_UNESCAPED_UNICODE);

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Authorization: Bearer '.LINE_CH_ACCESS_TOKEN
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 10,
  ]);
  $res = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ($http >= 200 && $http < 300);
}
