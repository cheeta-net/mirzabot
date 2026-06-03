<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../panels.php';
require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

$ManagePanel = new ManagePanel();
$setting = select("setting", "*");
$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data) || empty($data['payment_id']) || ($data['status'] ?? '') !== 'confirmed') {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'invalid callback']);
    exit;
}

$payment_id = htmlspecialchars($data['payment_id'], ENT_QUOTES, 'UTF-8');
$Payment_report = select("Payment_report", "*", "dec_not_confirmed", $payment_id, "select");

if ($Payment_report == false || $Payment_report['Payment_Method'] != 'PuzzleNetPay') {
    http_response_code(404);
    echo json_encode(['status' => false, 'msg' => 'payment not found']);
    exit;
}

if ($Payment_report['payment_Status'] == 'paid') {
    echo json_encode(['status' => true, 'msg' => 'already paid']);
    exit;
}

$textbotlang = languagechange('../text.json');
$invoice_id = $Payment_report['id_order'];
DirectPayment($invoice_id, "../images.jpg");
$pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackpuzzlenetpay", "select")['ValuePay'];
$Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
if ($pricecashback != "0") {
    $result = ($Payment_report['price'] * $pricecashback) / 100;
    $Balance_confrim = intval($Balance_id['Balance']) + $result;
    update("user", "Balance", $Balance_confrim, "id", $Balance_id['id']);
    $text_report = sprintf($textbotlang['paymentGateway']['giftReport'], $result);
    sendmessage($Balance_id['id'], $text_report, null, 'HTML');
}
update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
$paymentreports = select("topicid", "idreport", "report", "paymentreport", "select")['idreport'];
$price = number_format($Payment_report['price']);
$text_report = sprintf($textbotlang['paymentGateway']['reportPuzzleNetPay'], $Payment_report['id_user'], $Balance_id['username'], $price);
if (strlen($setting['Channel_Report']) > 0) {
    telegram('sendmessage', [
        'chat_id' => $setting['Channel_Report'],
        'message_thread_id' => $paymentreports,
        'text' => $text_report,
        'parse_mode' => "HTML"
    ]);
}

echo json_encode(['status' => true, 'msg' => 'ok']);
