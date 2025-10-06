<?php
// ใส่ Channel access token ของ LINE Messaging API
const LINE_CH_ACCESS_TOKEN = '7f0rLD4oN4UjV/DY535T4LbemrH+s7OT2lCxMk1dMJdWymlDgLvc89XZvvG/qBNg19e9/HvpKHsgxBFEHkXQlDQN5B8w3L0yhcKCSR51vfvTvUm0o5GQcq+jRlT+4TiQNN0DbIL2jI+adHfOz44YRQdB04t89/1O/w1cDnyilFU=';

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
