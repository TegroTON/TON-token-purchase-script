<br/>
<p align="center">
  <h3 align="center">Покупка TGR в Telegram боте</h3>

  <p align="center">
    PHP-cкрипт интеграции tegro.money для покупки токенов TGR в Телеграм ботах
    <br/>
    <br/>
    <a href="https://github.com/Lana4cool/TGR-purchase-script">View Demo</a>
    .
    <a href="https://github.com/Lana4cool/TGR-purchase-script/issues">Report Bug</a>
    .
    <a href="https://github.com/Lana4cool/TGR-purchase-script/issues">Request Feature</a>
  </p>
</p>



## Table Of Contents

* [About the Project](#about-the-project)
* [Built With](#built-with)
* [Getting Started](#getting-started)
  * [Installation](#installation)
* [License](#license)
* [Authors](#authors)


## About The Project

Данный PHP-cкрипт предназначен для интеграции функции покупки токенов TGR за фиат или криптовалюту через платежный гейт сервиса tegro.money. Это решение может быть легко внедрено в любой php скрипт управления Телеграм ботом.

## Built With

PHP 7.0+

## Getting Started

Скачайте файлы из репозитория и откройте их в редакторе кода.

### Installation

1. Заполните данные в начале файла tobot.php:
```php
########################
$tegromoney_shopid = "XXXXXXXXXXXXXXX"; // ID магазина на tegro.money
$tegromoney_secretkey = "XXXXXXXX"; // секретный ключ магазина на tegro.money
$minlimit = 150000; // минимальный лимит покупки TGR
$maxlimit = 850000; // максимальный лимит покупки TGR
########################
```

2. Впишите API TOKEN вашего бота в начале файла postback.php:
```php
###################
define('TOKEN', 'XXXXXXXXXXXXXXXXXXXXXXXXXXX');
###################
```
3. Заполните данные подключения к базе MySQL в файле global.php:
```php
$hostName = "";
$userName = "";
$password = "";
$databaseName = "";
```

4. Заполните поле "УРЛ уведомлений" в настройках магазина на tegro.money ссылкой на файл postback.php
Например: https://yourdomain/bot/postback.php

5. При необходимости создайте нужные таблицы в базе MySQL:
```php
paylinks
users
```

6. При необходимости заполните тело функций:
```php
function getTGRrate()
function saveTransaction($sum, $asset, $network, $type, $address)
```

7. Включите файл tobot.php в основной код вашего скрипта управления ботом черезе include.

8. Вызовите в нужном месте вашего кода функцию 
```php
buyTGRProcessSum($data);
```
При корректной настройке всех пунктов выше, она выведет пользователю сообщени с кнопкой для оплаты покупки TGR.


## License

Distributed under the MIT License. See [LICENSE](https://github.com/Lana4cool/TGR-purchase-script/blob/main/LICENSE.md) for more information.

## Authors

* **Lana Cool** - *Developer* - [Lana Cool](https://github.com/Lana4cool) - **
