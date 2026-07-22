<?php
/**
 * HyberX lead form endpoint.
 * GET  -> health check, so the deploy can be verified without sending mail.
 * POST -> validates the enquiry and emails it to the team.
 */

header('Content-Type: application/json; charset=utf-8');

$RECIPIENTS = ['Rahul@hyberx.com', 'support@hyberx.com'];
$FROM       = 'no-reply@hyberx.com';   // must be on this domain or the host will reject it

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['ok' => true, 'php' => PHP_VERSION, 'mail' => function_exists('mail')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

/* honeypot: real people never see this field, bots fill it */
if (!empty($_POST['website_url_confirm'])) {
    echo json_encode(['ok' => true]);   // pretend success, send nothing
    exit;
}

function clip($v, $max) {
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}

function field($key, $max = 500) {
    $v = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
    $v = str_replace(["\r", "\n"], ' ', $v);         // header-injection guard
    return clip($v, $max);
}

$name    = field('name', 120);
$email   = field('email', 160);
$phone   = field('phone', 40);
$company = field('company', 160);
$website = field('website', 300);
$service = field('service', 120);
$budget  = field('budget', 60);
$message = clip(trim((string) (isset($_POST['message']) ? $_POST['message'] : '')), 4000);

if ($name === '' || $phone === '' || $service === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_input']);
    exit;
}

$rows = [
    'Name'     => $name,
    'Email'    => $email,
    'Phone'    => $phone,
    'Company'  => $company !== '' ? $company : '—',
    'Website'  => $website !== '' ? $website : '—',
    'Service'  => $service,
    'Budget'   => $budget !== '' ? $budget : '—',
    'Message'  => $message !== '' ? $message : '—',
];

$body = "New growth call request from hyberx.com\n";
$body .= str_repeat('-', 44) . "\n\n";
foreach ($rows as $label => $value) {
    $body .= str_pad($label, 9) . ': ' . $value . "\n";
}
$body .= "\n" . str_repeat('-', 44) . "\n";
$body .= 'Submitted: ' . date('D, d M Y H:i:s T') . "\n";
$body .= 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";

$subject = 'Growth Call Request — ' . $name;
$headers = implode("\r\n", [
    'From: HyberX Website <' . $FROM . '>',
    'Reply-To: ' . $name . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=utf-8',
    'X-Mailer: PHP/' . PHP_VERSION,
]);

$sent = @mail(implode(', ', $RECIPIENTS), $subject, $body, $headers, '-f' . $FROM);

if (!$sent) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'send_failed']);
    exit;
}

echo json_encode(['ok' => true]);
