<?php

class TegroMoneyPaymentProcessor
{
    private $dbConnection;

    public function __construct(string $hostName, string $userName, string $password, string $databaseName)
    {
        $this->dbConnection = mysqli_connect($hostName, $userName, $password, $databaseName) or die ("Error connect to database");
    }

    public function processPayment(array $postData): void
    {
        foreach ($postData as $key => $value) {
            ${$key} = trim(filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS));
        }

        $orderParts = explode(':', $order_id);
        $chatId = $orderParts[0];
        $rowId = $orderParts[1];

        if (empty($chatId) || empty($rowId) || $status != 1) {
            return;
        }

        $result = mysqli_query($this->dbConnection, "SELECT * FROM `paylinks` WHERE `rowid`='$rowId' AND (`chatid` = '$chatId' AND `status` = '0')");

        if (mysqli_num_rows($result) > 0) {
            // The rest of your payment processing logic here...
        }
    }

    // Other methods here...
}

// Use the new class like this:

include "global.php";

$processor = new TegroMoneyPaymentProcessor($hostName, $userName, $password, $databaseName);

$processor->processPayment($_POST);
?>

