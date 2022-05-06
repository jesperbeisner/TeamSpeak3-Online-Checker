<?php

declare(strict_types=1);

if (!file_exists(__DIR__ . '/config.php')) {
    echo "Did you forget to rename 'config.php.dist' to 'config.php' and fill out the needed values?";
    exit(1);
}

$config = require __DIR__ . '/config.php';

$curlHandler = curl_init($config['TS3_URL']);

curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curlHandler, CURLOPT_HTTPHEADER, ['x-api-key: ' . $config['TS3_API_KEY']]);

$curlResponse = curl_exec($curlHandler);
$response = json_decode($curlResponse, true);

$onlineClients = [];
foreach ($response['body'] as $client) {
    $onlineClients[] = [
        'id' => $client['clid'],
        'database_id' => $client['client_database_id'],
        'nickname' => $client['client_nickname'],
        'unique_identifier' => $client['client_unique_identifier'],
    ];
}

if (!file_exists(__DIR__ . '/clients.json')) {
    file_put_contents(__DIR__ . '/clients.json', json_encode($onlineClients));
    echo 'First run, no clients.json file existed yet' . PHP_EOL;
    exit(0);
}

$jsonTextClients = file_get_contents(__DIR__ . '/clients.json');
$textClients = json_decode($jsonTextClients, true);

// Compare: Have new clients come online, have old clients gone offline?
// After that write the current OnlineClients into the clients.json file
// If something has changed, a Telegram message will be sent
$newOnlineClients = [];
foreach ($onlineClients as $onlineClient) {
    $found = false;
    foreach ($textClients as $textClient) {
        if ($textClient['unique_identifier'] === $onlineClient['unique_identifier']) {
            $found = true;
        }
    }

    // Client was not found, so has come online
    if ($found === false) {
        $newOnlineClients[] = $onlineClient;
    }
}

$newOfflineClients = [];
foreach ($textClients as $textClient) {
    $found = false;
    foreach ($onlineClients as $onlineClient) {
        if ($onlineClient['unique_identifier'] === $textClient['unique_identifier']) {
            $found = true;
        }
    }

    // Client was not found, so has gone offline
    if ($found === false) {
        $newOfflineClients[] = $textClient;
    }
}

// Overwrite old clients.json list with current OnlineClients
file_put_contents(__DIR__ . '/clients.json', json_encode($onlineClients));

// If something has changed, a Telegram message will be sent
if (count($newOnlineClients) > 0 || count($newOfflineClients) > 0) {
    $dateAndTime = (new DateTime())->format('Y-m-d H:i:s');
    $dateAndTime = str_replace('-', '\-', $dateAndTime);
    $text = "*Update $dateAndTime*" . PHP_EOL . PHP_EOL;

    if (count($newOnlineClients) > 0) {
        $text .= '*Newly came online*' . PHP_EOL;
        foreach ($newOnlineClients as $newOnlineClient) {
            $text .= 'Nickname: ' . $newOnlineClient['nickname'] . PHP_EOL;
        }

        if (count($newOfflineClients) > 0) {
            $text .= PHP_EOL . PHP_EOL;
        }
    }

    if (count($newOfflineClients) > 0) {
        $text .= '*Newly went offline*' . PHP_EOL;
        foreach ($newOfflineClients as $newOfflineClient) {
            $text .= 'Nickname: ' . $newOfflineClient['nickname'] . PHP_EOL;
        }
    }

    $payload = ['chat_id' => $config['TELEGRAM_CHAT_ID'], 'text' => $text, 'parse_mode' => 'MarkdownV2'];
    $curlHandler = curl_init($config['TELEGRAM_URL']);
    curl_setopt($curlHandler, CURLOPT_POST, true);
    curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlHandler, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($curlHandler, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_exec($curlHandler);
}

echo 'Success' . PHP_EOL;
exit(0);