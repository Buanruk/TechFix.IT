<?php
// push_test.php  — ใช้ทดสอบการส่ง push แบบตรงๆ
// เรียก: https://techfix.asia/push_test.php?key=YOUR_KEY&to=Uxxxxxxxx&msg=hello

if (($_GET['key'] ?? '') !== 'SET_A_TEMP_KEY_HERE') { http_response_code(403); exit('forbidden'); }

$to  = $_GET['to'] ?? '';
$msg = $_GET['msg'] ?? 'test';
$token = 'PUT_YOUR_LINE_CHANNEL_ACCESS_TOKEN_HERE';

if (!$to) exit('missing to');

$payload = json_encode(['to'=>$to,'messages'=>[['type'=>'text','text'=>$msg]]], JSON_UNESCAPED_UNICODE);
$ch = curl_init('https://api.line.me/v2/bot/message/push');
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json','Authorization: Bearer '.$token],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POSTFIELDS => $payload,
]);
$res  = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

header('Content-Type: text/plain; charset=utf-8');
echo "HTTP: $http\nERR: $err\nRES: $res\n";
