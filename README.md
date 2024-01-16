# TGR Purchase Script for Telegram Bots

This PHP script enables the integration of tegro.money's payment gateway for purchasing TGR tokens in Telegram bots. It's designed for easy implementation into any PHP-based Telegram bot management script.

## Table of Contents

- [About the Project](#about-the-project)
- [Built With](#built-with)
- [Getting Started](#getting-started)
  - [Installation](#installation)
- [License](#license)
- [Authors](#authors)

## About the Project

The TGR purchase script is a PHP-based solution that facilitates the buying of TGR tokens using fiat or cryptocurrency through the tegro.money payment gateway. This script is designed to be seamlessly integrated into any PHP script managing Telegram bots.

## Built With

- PHP 7.0 or higher

## Getting Started

Download the files from the repository and open them in your code editor.

### Installation

1. Fill in the details at the beginning of the `tobot.php` file:
    ```php
    ########################
    $tegromoney_shopid = "YourShopID"; // Shop ID on tegro.money
    $tegromoney_secretkey = "YourSecretKey"; // Secret key of the shop on tegro.money
    $minlimit = 150000; // Minimum TGR purchase limit
    $maxlimit = 850000; // Maximum TGR purchase limit
    ########################
    ```

2. Enter the API TOKEN of your bot at the beginning of the `postback.php` file:
    ```php
    ###################
    define('TOKEN', 'YourBotApiToken');
    ###################
    ```

3. Fill in the MySQL database connection details in the `global.php` file:
    ```php
    $hostName = "YourHostName";
    $userName = "YourUserName";
    $password = "YourPassword";
    $databaseName = "YourDatabaseName";
    ```

4. In the shop settings on tegro.money, set the "Notification URL" to point to your `postback.php` file. For example: `https://yourdomain/bot/postback.php`

5. If necessary, create the required tables in your MySQL database:
    ```sql
    CREATE TABLE paylinks (...);
    CREATE TABLE users (...);
    ```

6. Optionally, fill in the body of functions:
    ```php
    function getTGRrate() { ... }
    function saveTransaction($sum, $asset, $network, $type, $address) { ... }
    ```

7. Include the `tobot.php` file in the main code of your bot management script using `include`.

8. In the appropriate part of your code, call the function `buyTGRProcessSum($data);`. When properly set up, this function will display a message to the user with a button to purchase TGR.

## License

This project is distributed under the MIT License. See [LICENSE](https://github.com/Lana4cool/TGR-purchase-script/blob/main/LICENSE.md) for more information.

## Authors

- **Lana Cool** - *Developer* - [Lana Cool](https://github.com/Lana4cool) 

