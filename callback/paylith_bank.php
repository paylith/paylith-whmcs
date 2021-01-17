<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayModuleName = basename(__FILE__, '.php');

$gatewayParams = getGatewayVariables($gatewayModuleName);

if (! $gatewayParams['type']) {
    die('Paylith module not activated!');
}

if (! isset($_POST)) {
    die('Bad request');
}

$orderId = isset($_POST['orderId']) ? $_POST['orderId'] : null;
$paymentAmount = isset($_POST['paymentAmount']) ? $_POST['paymentAmount'] : null;
$conversationId = isset($_POST['conversationId']) ? $_POST['conversationId'] : null;
$userId = isset($_POST['userId']) ? $_POST['userId'] : null;
$status = isset($_POST['status']) ? $_POST['status'] : null;
$hash = isset($_POST['hash']) ? $_POST['hash'] : null;

if (! $conversationId || ! $orderId || ! $paymentAmount || ! $status || ! $userId || ! $hash) {
    die('Bad request');
}

$paylithApiKey = $gatewayParams['paylithBankApiKey'];
$paylithApiSecret = $gatewayParams['paylithBankApiSecret'];

$parameters = [
    'orderId' => $orderId,
    'paymentAmount' => $paymentAmount,
    'conversationId' => $conversationId,
    'userId' => $userId,
    'status' => $status,
];

ksort($parameters);

$hashString = implode('|', $parameters);
$generatedHash = hash_hmac('sha256', $hashString.$paylithApiSecret, $paylithApiKey);
$generatedHash = hash_hmac('md5', $generatedHash, $paylithApiKey);

if ($generatedHash != $hash) {
    die('Hash wrong');
}

$invoiceId = checkCbInvoiceID($conversationId, $gatewayParams['name']);

$transactionId = checkCbTransID($invoiceId);

logTransaction($gatewayParams['name'], $_POST, '200');

addInvoicePayment($invoiceId, $transactionId, $paymentAmount, "", $gatewayParams['name'], "on");

echo 'OK';
