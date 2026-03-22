<?php
#Version 0.5
echo "whisper cat server Version 0.5 (c) Minxi Wan。\n";
use Workerman\Worker;
require 'Workerman/Autoloader.php';
const __SERVER_IP_PORT__ = '192.168.0.102:13173';
$logFile=null;
if(!file_exists('config')){
    mkdir('config',0777,true);
    echo "文件夹 'config' 创建成功;";
}
$logFile=fopen('./config/z-'.date('Y-m-j').'.txt','a+');
function extractKey($publicKey) {
    // 移除公钥的头尾标识和所有空白字符
    return str_replace(
        ['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', ' ', "\n", "\r", "\t"],
        ['', '', '', '', '', ''],
        $publicKey
    );
}
function packMessage($array){
    return json_encode($array,JSON_UNESCAPED_UNICODE);
}
function checkJsonData($value) {
    $res = json_decode($value, true);
    $error = json_last_error();
    if (!empty($error)) {
        return false;
    }else{
        return $res;
    }
}
function pong(){
    return ["type"=>"pong", "data"=>"", "key"=>""];
}
function handle_message($connection,$data){
    global $socket_worker;
    $JsonData=checkJsonData($data);
    if(!is_array($JsonData))return false;
    if(!array_key_exists("type",$JsonData))return false;
    if(!array_key_exists("data",$JsonData))return false;
    if(!array_key_exists("key",$JsonData))return false;
    if(!is_string($JsonData["type"]))return false;
    if(!is_string($JsonData["data"])) return false;
    if(!is_string($JsonData["key"]))return false;
    $key=$JsonData['key'];
    switch ($JsonData['type']){
        case 'ping':{
            $connection->send(packMessage(pong()));
            break;
        }
        case 'forward':{//转发消息
            #1.检查请求者的key是否存在
            if($connection->publickey===''){
                break;
            }
            foreach ($socket_worker->connections as $index => $con) {
                $extractK=extractKey($key);//需要转发的对象的ext key
                if($extractK===$con->extPublickey){//找到需要转发的对象
                    $JsonData["key"]=$connection->publickey;//替换公钥为请求者的公钥
                    $con->send(packMessage($JsonData));//发送给接收者
                    break;
                }
            }
            break;
        }
        case 'publickey':{//登记账号
            #1.检查请求者的key是否存在
            if($connection->publickey!==''){//重复登记
                return false;
            }
            if($connection->extPublickey!==''){//重复登记
                return false;
            }
            $connection->publickey=$JsonData['key'];
            $connection->extPublickey=extractKey($JsonData['key']);
            break;
        }
        case 'broadcast':{
            break;
        }
    }
}
function handle_connection($connection){
    $ip=$connection->getRemoteIp();
    $id=$connection->id;
    $connection->publickey='';//初始化此连接的公钥
    $connection->extPublickey='';//初始化此连接的公钥
    createLog('link_in',['connectionId'=>$id,'connectionIp'=>$ip]);
}
function handle_close($connection){
    $ip=$connection->getRemoteIp();
    $id=$connection->id;
    createLog('link_out',['connectionId'=>$id,'connectionIp'=>$ip]);
}
function createDate(){
    $date=getdate();
    $mon=sprintf('%02d',$date['mon']);
    $day=sprintf('%02d',$date['mday']);
    $hours=sprintf('%02d',$date['hours']);
    $minutes=sprintf('%02d',$date['minutes']);
    $seconds=sprintf('%02d',$date['seconds']);
    return "{$date['year']}-{$mon}-{$day} {$hours}:{$minutes}:{$seconds}";
}
function createLog($logType,$logData){
    global $logFile;
    $time=createDate();
    switch ($logType){
        case 'link_in':{
            $log=<<<ETX
{$time}--连接Id为:{$logData['connectionId']},连接地址为:{$logData['connectionIp']},连接到服务器;

ETX;
            echo $log;fwrite($logFile,$log);break;
        }
        case 'link_out':{
            $log=<<<ETX
{$time}--连接Id为:{$logData['connectionId']},连接地址为:{$logData['connectionIp']},断开服务器连接;

ETX;
            echo $log;fwrite($logFile,$log);break;
        }
    }
}
$socket_worker=new Worker('websocket://'.__SERVER_IP_PORT__);
$socket_worker->count=1;
$socket_worker->onConnect='handle_connection';
$socket_worker->onMessage='handle_message';
$socket_worker->onClose='handle_close';
Worker::runAll();