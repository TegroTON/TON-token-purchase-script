<?php

include "global.php";
define('TOKEN', 'XXXXXXXXXXXXXXXXXXXXXXXXXXX');
define('FILE_NAME', "tm_response.txt");
define('PAYMENT_SUCCESS_STATUS', 1);
define('MARKUP_PERCENTAGE', 105);

class Database {
    private $link;
    public function __construct($hostName, $userName, $password, $databaseName) {
        $this->link = mysqli_connect($hostName, $userName, $password, $databaseName) or die ("Error connect to database");
    }
    
    public function execute($query) {
        return mysqli_query($this->link, $query);
    }
    
    public function fetchObject($result) {
        return @mysqli_fetch_object($result);
    }
    
    public function rowsCount($result) {
        return mysqli_num_rows($result);
    }
}

function logPostData($fileName) {
    $toFile = '============' . PHP_EOL . date('d/m/Y G:i') . PHP_EOL;
    foreach($_POST as $key => $value) {
        $toFile .= "{$key}:{$value}" . PHP_EOL;
    }

    file_put_contents($fileName, $toFile);
}

function calculateTGR($amount, $tgrRate) {
    $sumInTGR = $amount / $tgrRate;
    return $sumInTGR / MARKUP_PERCENTAGE * 100;
}

function sendIt($response, $type){
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/'.$type);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
}

function send($id, $message, $keyboard = null) {
    $data = [
        'chat_id' => $id,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];

    if ($keyboard) {
        if ($keyboard == "DEL") {
            $keyboard = ['remove_keyboard' => true];
        }
        $encodedMarkup = json_encode($keyboard);
        $data['reply_markup'] = $encodedMarkup;
    }

    sendIt($data, 'sendMessage');
}

function getTGRrate() {
    // Implement fetch rate logic here
    return 0;
}

function saveTransaction($sum, $asset, $network, $type, $address) {
    // Implement save transaction logic here
}

// Start here
$db = new Database($hostName, $userName, $password, $databaseName);

logPostData(FILE_NAME);

list($chatId, $rowId) = explode(":", $_POST['order_id']);
$status = (int) $_POST['status'];

if (!empty($chatId) && !empty($rowId) && $status === PAYMENT_SUCCESS_STATUS) {
    $selectPaylinksQuery = "SELECT * FROM `paylinks` WHERE `rowid`='$rowId' AND (`chatid` = '$chatId' AND `status` = '0')";
    $result = $db->execute($selectPaylinksQuery);

    if ($db->rowsCount($result) > 0) {
        $updatePaylinksQuery = "UPDATE `paylinks` SET `status`='1' WHERE `rowid`='$rowId'";
        $db->execute($updatePaylinksQuery);
        
        $tgrRate = getTGRrate();
        $cleanSumTGR = calculateTGR($_POST['amount'], $tgrRate);

        $selectUserQuery = "SELECT * FROM `users` WHERE `chatid`='$chatId'";
        $userResult = $db->execute($selectUserQuery);
        $userData = $db->fetchObject($userResult);
        
        $newBalanceTGR = $userData->tgr_ton_full + $cleanSumTGR;
        $updateUserQuery = "UPDATE `users` SET `tgr_ton_full`='$newBalanceTGR' WHERE `chatid`='$chatId'";
        $db->execute($updateUserQuery);
        
        saveTransaction($cleanSumTGR, "TGR", "TON", "buy", 0);

        $message = "Поступил платеж в сумме {$_POST['amount']} USD на покупку токенов TGR. $cleanSumTGR TGR зачислено на твой баланс.";
        send($chatId, $message);
    }
}

