<?php

header("Content-Type: application/json");

$data=json_decode(file_get_contents("php://input"),true);

$prompt=$data["prompt"] ?? "";

$apiKey="ak_2Jj54c7iX7Jj6Aj0340Ko9Zg1Xj6w";

$url="https://api.longcat.chat/openai/v1/chat/completions";

$postData=[

"model"=>"LongCat-2.0",

"messages"=>[

[
"role"=>"user",
"content"=>$prompt
]

],

"temperature"=>0.7,
"max_tokens"=>2000,
"stream"=>false

];

$ch=curl_init($url);

curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

curl_setopt($ch,CURLOPT_POST,true);

curl_setopt($ch,CURLOPT_HTTPHEADER,[

"Authorization: Bearer ".$apiKey,
"Content-Type: application/json"

]);

curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($postData));

$response=curl_exec($ch);

$error=curl_error($ch);

$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);

curl_close($ch);

if($error){

echo json_encode([
"error"=>$error
]);
exit;

}

$json=json_decode($response,true);

if($http!=200){

echo json_encode($json);
exit;

}

echo json_encode([

"answer"=>$json["choices"][0]["message"]["content"]

]);