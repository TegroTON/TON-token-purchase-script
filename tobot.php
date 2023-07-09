<?php

class TegroMoneyBot
{
    private $shopId;
    private $secretKey;
    private $minLimit;
    private $maxLimit;
    private $link;
    private $chatId;

    public function __construct(string $shopId, string $secretKey, int $minLimit, int $maxLimit)
    {
        $this->shopId = $shopId;
        $this->secretKey = $secretKey;
        $this->minLimit = $minLimit;
        $this->maxLimit = $maxLimit;
    }

    public function processSum(array $data): void
    {
        $this->chatId = $data['message']['chat']['id'];
        $sumTGR = trim(intval($data['message']['text']));

        if ($sumTGR < $this->minLimit) {
            $this->sendMessage("❌ Ошибка! Минимальная сумма для покупки TRG: {$this->minLimit}. Повторите попытку.");
        } elseif ($sumTGR > $this->maxLimit) {
            $this->sendMessage("❌ Ошибка! Максимальная сумма для покупки TRG: {$this->maxLimit}. Повторите попытку.");
        } else {
            // Your code for handling the correct sum goes here.
        }
    }

    private function sendMessage(string $text): void
    {
        $response = [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        sendit($response, 'sendMessage');
    }

    // More methods here...
}

$tegromoneyShopId = "XXXXXXXXXXXXXXX"; // ID магазина на tegro.money
$tegromoneySecretKey = "XXXXXXXX"; // секретный ключ магазина на tegro.money
$minLimit = 150000; // минимальный лимит покупки TGR
$maxLimit = 850000; // максимальный лимит покупки TGR

$bot = new TegroMoneyBot($tegromoneyShopId, $tegromoneySecretKey, $minLimit, $maxLimit);

// Ваш код для получения данных из Телеграмма и вызова $bot->processSum($data) здесь...
?>

