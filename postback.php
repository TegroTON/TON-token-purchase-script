<?php
###################
define('TOKEN', 'XXXXXXXXXXXXXXXXXXXXXXXXXXX');
###################

// По желанию входящие данные сохраняются в файл tm_response.txt
$tofile = '
============
'.date('d/m/Y G:i').'
';

foreach($_POST AS $key => $value) {
    ${$key} = trim(filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS));
    $tofile .= $key.':'.$value.'
';
} // end FOREACH

if($file = fopen("tm_response.txt", "w+")){
    fputs($file, $tofile);
    fclose($file);
} // end frite to file

include "global.php";
$link = mysqli_connect($hostName, $userName, $password, $databaseName) or die ("Error connect to database");

$p = explode(":",$order_id);
$chat_id = $p[0];
$rowid =  $p[1];

if(!empty($chat_id) && !empty($rowid) && $status == 1){

  //При формировании ссылки на оплату скрипт производит запись в базу MySQL данных о платеже. При получении постбека происходит всерка с данной записью, чтобы исключить поддельные или повторные запросы постбек.
    $str2select = "SELECT * FROM `paylinks` WHERE `rowid`='$rowid' AND (`chatid` = '$chat_id' AND `status` = '0')";
    $result = mysqli_query($link, $str2select);
    if(mysqli_num_rows($result) > 0){

        $str2upd = "UPDATE `paylinks` SET `status`='1' WHERE `rowid`='$rowid'";
        mysqli_query($link, $str2upd);

        $tgrrate = getTGRrate();
        $suminTGR = $amount / $tgrrate;
        $cleansumTGR = $suminTGR / 105 * 100;

        $str3select = "SELECT * FROM `users` WHERE `chatid`='$chat_id'";
        $result3 = mysqli_query($link, $str3select);
        $row3 = @mysqli_fetch_object($result3);

        $newbalanceTGR = $row3->tgr_ton_full + $cleansumTGR;
        $str3upd = "UPDATE `users` SET `tgr_ton_full`='$newbalanceTGR' WHERE `chatid`='$chat_id'";
        mysqli_query($link, $str3upd);

        // По желанию можно сохранять данные о транзакции
        saveTransaction($cleansumTGR, "TGR", "TON", "buy", 0);

        $response = array(
            'chat_id' => $chat_id,
            'text' => "Поступил платеж в сумме $amount USD на покупку токенов TGR.
$cleansumTGR TGR зачислено на твой баланс.",
            'parse_mode' => 'HTML');
        sendit($response, 'sendMessage');

    }
}

function sendit($response, $restype){
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/'.$restype);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
}

function send($id, $message, $keyboard) {

    //Удаление клавы
    if($keyboard == "DEL"){
        $keyboard = array(
            'remove_keyboard' => true
        );
    }
    if($keyboard){
        //Отправка клавиатуры
        $encodedMarkup = json_encode($keyboard);

        $data = array(
            'chat_id'      => $id,
            'text'     => $message,
            'reply_markup' => $encodedMarkup,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => True
        );
    }else{
        //Отправка сообщения
        $data = array(
            'chat_id'      => $id,
            'text'     => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => True
        );
    }

    $out = sendit($data, 'sendMessage');
    return $out;
}

function getTGRrate(){
    global $link;

    // В данной функции реализуется получение курса TGR к USD например через запрос к:
    // https://api.coingecko.com/api/v3/simple/price?ids=tegro&vs_currencies=usd
    // либо к сохраненному ранее курсу в базе MySQL

    return $tgrRate;
}
function saveTransaction($sum, $asset, $network, $type, $address){
    global $chat_id, $link;

    // В данной функции выполняется сохранение данных транзакции в базу MySQL например так:
    //$curtime = time();
    //$str2ins = "INSERT INTO `transactions` (`chatid`,`times`, `asset`, `network`, `sum`, `type`, `address`) VALUES ('$chat_id','$curtime', '$asset', '$network', '$sum', '$type', '$address')";
    //mysqli_query($link, $str2ins);
}
 ?>
