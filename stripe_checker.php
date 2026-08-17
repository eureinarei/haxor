<?php
header('Content-Type: application/json');
$action = $_GET['action'] ?? '';
if ($action !== 'check') {
    echo json_encode(['ok' => false, 'msg' => 'Ação inválida']);
    exit;
}
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$line = $data['card_line'] ?? '';
$parts = array_map('trim', explode('|', $line));
if (count($parts) < 4) {
    echo json_encode(['ok' => false, 'msg' => 'Formato inválido']);
    exit;
}
[$number, $expMonth, $expYear, $cvc] = $parts;
if (!preg_match('/^\d+$/', $number) || !preg_match('/^\d{1,2}$/', $expMonth) || !preg_match('/^\d{2,4}$/', $expYear) || !preg_match('/^\d{3,4}$/', $cvc)) {
    echo json_encode(['ok' => false, 'msg' => 'Dados inválidos']);
    exit;
}
$expY = strlen($expYear) === 2 ? '20' . $expYear : $expYear;
$pk = 'pk_live_51TrmHoLJuAfa1w8I3yQ5RB0K46lz4VMOdCJRQRb1oy9Z9PwA5jBa8OQbZDetU5SJUMa6zqIzR57Kg7wx24msCCnn00yHqpN4St';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.stripe.com/v1/payment_methods',
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $pk,
        'Content-Type: application/x-www-form-urlencoded'
    ],
    CURLOPT_POSTFIELDS => http_build_query([
        'type' => 'card',
        'card[number]' => $number,
        'card[exp_month]' => $expMonth,
        'card[exp_year]' => $expY,
        'card[cvc]' => $cvc
    ]),
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);
$res1 = curl_exec($ch);
if (curl_errno($ch)) {
    echo json_encode(['ok' => false, 'msg' => 'cURL: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);
$pm = json_decode($res1, true);
if (!isset($pm['id'])) {
    echo json_encode(['ok' => false, 'msg' => $pm['error']['message'] ?? 'Invalid']);
    exit;
}
$ch2 = curl_init();
curl_setopt_array($ch2, [
    CURLOPT_URL => 'https://api.stripe.com/v1/payment_intents',
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $pk,
        'Content-Type: application/x-www-form-urlencoded'
    ],
    CURLOPT_POSTFIELDS => http_build_query([
        'amount' => '100',
        'currency' => 'usd',
        'payment_method' => $pm['id'],
        'confirmation_method' => 'manual',
        'confirm' => 'true',
        'capture_method' => 'manual'
    ]),
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);
$res2 = curl_exec($ch2);
if (curl_errno($ch2)) {
    echo json_encode(['ok' => false, 'msg' => 'cURL PI: ' . curl_error($ch2)]);
    curl_close($ch2);
    exit;
}
curl_close($ch2);
$pi = json_decode($res2, true);
if (isset($pi['status']) && ($pi['status'] === 'succeeded' || $pi['status'] === 'requires_action' || isset($pi['charges']))) {
    echo json_encode(['ok' => true, 'msg' => 'LIVE']);
    exit;
}
echo json_encode(['ok' => false, 'msg' => $pi['error']['message'] ?? 'Declined']);
