<?php
########################
$tegromoney_shopid = "XXXXXXXXXXXXXXX"; // ID магазина на tegro.money
$tegromoney_secretkey = "XXXXXXXX"; // секретный ключ магазина на tegro.money
$minlimit = 150000; // минимальный лимит покупки TGR
$maxlimit = 850000; // максимальный лимит покупки TGR
########################

// Данный код должен быть внедрен в скрипт управления Телеграм ботом в то место, в котором скрипт принимает от пользователя сумму перевода в элементе массива: $data['message']['text']
// Для вывода сообщения с кнопкой оплаты вызывается функция:
buyTGRProcessSum($data);

function buyTRGmakeLink($sum){
    global $link, $chat_id, $tegromoney_shopid, $tegromoney_secretkey;

    $curtime = time();
    $str2ins = "INSERT INTO `paylinks` (`chatid`,`times`,`status`,`sum`) VALUES ('$chat_id','$curtime','0','$sum')";
    mysqli_query($link, $str2ins);
    $last_id = mysqli_insert_id($link);

    $currency = 'USD';
    $order_id = $chat_id.':'.$last_id;

    $data = array(
        'shop_id'=>$tegromoney_shopid,
        'amount'=>$sum,
        'currency'=>$currency,
        'order_id'=>$order_id
        #'test'=>1
    );
    ksort($data);
    $str = http_build_query($data);
    $sign = md5($str . $tegromoney_secretkey);

    $link = 'https://tegro.money/pay/?'.$str.'&sign='.$sign;
    return $link;
}

function buyTGRProcessSum($data){
    global $link, $chat_id, $minlimit, $maxlimit;

    $sumTGR = trim(intval($data['message']['text']));
    if ($sumTGR < $minlimit){
        $response = array(
            'chat_id' => $chat_id,
            'text' => "❌ОШИБКА! Минимальная сумма для покупки TRG: $minlimit. Повтори попытку.",
            'parse_mode' => 'HTML');
        sendit($response, 'sendMessage');
    }
    elseif ($sumTGR > $maxlimit){
        $response = array(
            'chat_id' => $chat_id,
            'text' => "❌ОШИБКА! Максимальная сумма для покупки TRG: $maxlimit. Повтори попытку.",
            'parse_mode' => 'HTML');
        sendit($response, 'sendMessage');
    }else{
        clean_temp_sess();
        $tgrrate = getTGRrate();
        $fee = $sumTGR / 100 * 5;
        $sum = round(($sumTGR + $fee) * $tgrrate, 2);

        $link = buyTRGmakeLink($sum);
        $a["inline_keyboard"][0][0]["text"] = "Оплатить $sumTGR TGR";
        $a["inline_keyboard"][0][0]["url"] = rawurldecode($link);
        $a["inline_keyboard"][1][0]["callback_data"] = 25;
        $a["inline_keyboard"][1][0]["text"] = "⏪ Назад";
        send($chat_id, "Перейди по кнопке для оплаты покупки $sumTGR TGR.
<i>Важно: курс TGR фиксируется непосредственно в момент поступления оплаты.
Комиссия за операцию: 5% от суммы.</i>", $a);
    }
}
 ?>
