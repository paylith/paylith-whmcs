<?php

if (! defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function paylith_cc_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Paylith - Kredi Kartı / Banka Kartı'
        ],
        'paylithCreditApiKey' => [
            'FriendlyName' => 'Mağaza Parola (API Key)',
            'Type' => 'text',
            'Size' => '25',
            'Description' => 'Paylith.com üzerinde kayıtlı olan mağazanın parolası.',
        ],
        'paylithCreditApiSecret' => [
            'FriendlyName' => 'Mağaza Gizli Anahtar (API Secret)',
            'Type' => 'text',
            'Size' => '25',
            'Description' => 'Paylith.com üzerinde kayıtlı olan mağazanın gizli anahtarı.',
        ],
    ];
}

function paylith_cc_link($params)
{
    $clientDetails = $params['clientdetails'];

    $paylithApiKey = $params['paylithCreditApiKey'];
    $paylithApiSecret = $params['paylithCreditApiSecret'];
    $paylithGatewayToken = paylithCreditGenerateHash(
        $paylithApiKey,
        $paylithApiSecret,
        $params['invoiceid'],
        $clientDetails['userid'],
        $clientDetails['email'],
        paylithCreditGetIpAddress(),
    );

    $postFields = [
        'apiKey' => $paylithApiKey,
        'token' => $paylithGatewayToken,
        'conversationId' => (string) $params['invoiceid'],
        'userId' => $clientDetails['userid'],
        'userEmail' => $clientDetails['email'],
        'userIpAddress' => paylithCreditGetIpAddress(),
        'productApi' => true,
        'productData' => [
            'name' => $params['description'],
            'amount' => $params['amount'] * 100,
            'paymentChannels' => [1],
        ],
    ];

    $response = paylithCreditSendRequest($postFields);
    $response = json_decode($response);

    return '
        <!-- Paylith Credit Card -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izimodal-1.6.0@1.6.1/css/iziModal.min.css">

        <script src="https://cdn.jsdelivr.net/npm/izimodal-1.6.0@1.6.1/js/iziModal.min.js"></script>

        <button id="openPaylithModal" class="btn btn-success" type="button">'.$params['langpaynow'].'</button>

        <script>
        $("#paylithModal").iziModal({
            iframe: true,
            iframeURL: "'.$response->paymentLink.'",
            title: "Paylith Ortak Ödeme Sayfası",
            iframeHeight: 800,
            width: 950,
            headerColor: "linear-gradient(-45deg,#b486ff,#7200ff 60%,#7200ff 99%)",
        });

        $(document).on("click", "#openPaylithModal", function (e) {
            e.preventDefault();
            console.log("triggered btn");
            $("#paylithModal").iziModal("open");
        });
        </script>
        <!-- /Paylith Credit Card -->
    ';
}

function paylithCreditGetIpAddress()
{
    if (getenv("HTTP_CLIENT_IP")) {
        $ip = getenv("HTTP_CLIENT_IP");
    } elseif (getenv("HTTP_X_FORWARDED_FOR")) {
        $ip = getenv("HTTP_X_FORWARDED_FOR");
        if (strstr($ip, ',')) {
            $tmp = explode(',', $ip);
            $ip = trim($tmp[0]);
        }
    } else {
        $ip = getenv("REMOTE_ADDR");
    }

    return $ip;
}

function paylithCreditGenerateHash(
    string $apiKey,
    string $apiSecret,
    string $conversationId,
    string $userId,
    string $userEmail,
    string $userIpAddress
) {
    $hashStr = [
    'apiKey' => $apiKey,
    'conversationId' => $conversationId,
    'userEmail' => $userEmail,
    'userId' => $userId,
    'userIpAddress' => $userIpAddress,
  ];

    $hash = hash_hmac('sha256', implode('|', $hashStr) . $apiSecret, $apiKey);
    return hash_hmac('md5', $hash, $apiKey);
}

function paylithCreditSendRequest(array $payload)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.paylith.com/v1/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
    curl_setopt($ch, CURLOPT_SSLVERSION, 6); // Force TLSv1.2
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    $response = curl_exec($ch);

    curl_close($ch);

    return $response;
}
