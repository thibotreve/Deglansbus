<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Honeypot
if (!empty($_POST['_honing'])) {
    echo json_encode(['success' => true]);
    exit;
}

// Tijdcheck (< 2 seconden = bot)
if (!empty($_POST['_start']) && (time() * 1000 - (int)$_POST['_start']) < 2000) {
    echo json_encode(['success' => true]);
    exit;
}

$naam     = trim($_POST['naam']     ?? '');
$email    = trim($_POST['email']    ?? '');
$telefoon = trim($_POST['telefoon'] ?? '');
$dienst   = trim($_POST['dienst']   ?? '');
$bericht  = trim($_POST['bericht']  ?? '');

if (empty($naam) || empty($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Naam en e-mail zijn verplicht.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ongeldig e-mailadres.']);
    exit;
}

if (strlen($naam) > 100 || strlen($email) > 200) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invoer te lang.']);
    exit;
}

// Lead opslaan
$dataDir   = __DIR__ . '/data';
$leadsFile = $dataDir . '/leads.json';
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);

$leads = [];
if (file_exists($leadsFile)) {
    $leads = json_decode(file_get_contents($leadsFile), true) ?? [];
}

$maxId = 0;
foreach ($leads as $l) {
    if (($l['id'] ?? 0) > $maxId) $maxId = $l['id'];
}

$ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0];

$lead = [
    'id'           => $maxId + 1,
    'naam'         => $naam,
    'email'        => $email,
    'telefoon'     => $telefoon,
    'dienst'       => $dienst,
    'bericht'      => $bericht,
    'ip'           => trim($ip),
    'gelezen'      => false,
    'aangemaakt_op'=> date('d/m/Y H:i'),
];

array_unshift($leads, $lead);
file_put_contents($leadsFile, json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// E-mail versturen via Gmail SMTP
$dienstLabels = [
    'exterior'   => 'Exterior Detail – €69',
    'prowash'    => 'Glansbus Pro Wash – €99',
    'fulldetail' => 'Glansbus Full Detail – Vanaf €129',
    'andere'     => 'Andere / Vraag',
];
$dienstTekst = $dienstLabels[$dienst] ?? ($dienst ?: '–');

$html = "
<div style='font-family:sans-serif;max-width:580px;color:#1a1c1e;'>
  <div style='background:#1a1c1e;padding:24px 32px;border-radius:12px 12px 0 0;'>
    <h2 style='color:#fff;margin:0;font-size:1.1rem;'>Nieuwe contactaanvraag</h2>
  </div>
  <div style='border:1px solid #e5e7eb;border-top:none;padding:32px;border-radius:0 0 12px 12px;'>
    <table style='width:100%;border-collapse:collapse;font-size:0.95rem;'>
      <tr><td style='padding:10px 0;color:#6b7280;width:110px;'>Naam</td><td style='padding:10px 0;font-weight:600;'>".htmlspecialchars($naam)."</td></tr>
      <tr style='border-top:1px solid #f3f4f6;'><td style='padding:10px 0;color:#6b7280;'>E-mail</td><td style='padding:10px 0;'><a href='mailto:".htmlspecialchars($email)."'>".htmlspecialchars($email)."</a></td></tr>
      <tr style='border-top:1px solid #f3f4f6;'><td style='padding:10px 0;color:#6b7280;'>Telefoon</td><td style='padding:10px 0;'>".htmlspecialchars($telefoon ?: '–')."</td></tr>
      <tr style='border-top:1px solid #f3f4f6;'><td style='padding:10px 0;color:#6b7280;'>Dienst</td><td style='padding:10px 0;'>".htmlspecialchars($dienstTekst)."</td></tr>
      <tr style='border-top:1px solid #f3f4f6;'><td style='padding:10px 0;color:#6b7280;vertical-align:top;'>Bericht</td><td style='padding:10px 0;'>".nl2br(htmlspecialchars($bericht ?: '–'))."</td></tr>
    </table>
    <p style='margin-top:24px;color:#9ca3af;font-size:0.8rem;'>Lead #".$lead['id']." · ".$lead['aangemaakt_op']."</p>
  </div>
</div>";

stuurGmailSMTP(
    'thibotreve@gmail.com',
    "Nieuwe aanvraag van $naam",
    $html,
    'thibotreve@gmail.com',
    'npbp puui gzeg cjxj'
);

echo json_encode(['success' => true, 'message' => 'Bedankt! We nemen zo snel mogelijk contact op.']);

// ── Gmail SMTP ────────────────────────────────────────────────────────────────
function stuurGmailSMTP($to, $subject, $html, $gmailUser, $gmailPass) {
    $smtp = @fsockopen('ssl://smtp.gmail.com', 465, $errno, $errstr, 10);
    if (!$smtp) return false;

    $r = function() use ($smtp) { return fgets($smtp, 512); };
    $s = function($c) use ($smtp, $r) { fputs($smtp, $c . "\r\n"); return $r(); };

    $r();
    $s("EHLO deglansbus.be");
    while (($line = $r()) && substr($line, 3, 1) === '-') {}
    $s("AUTH LOGIN");
    $s(base64_encode($gmailUser));
    $s(base64_encode($gmailPass));
    $s("MAIL FROM: <$gmailUser>");
    $s("RCPT TO: <$to>");
    $s("DATA");

    $msg = "From: De Glansbus <$gmailUser>\r\n"
         . "To: $to\r\n"
         . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: text/html; charset=UTF-8\r\n"
         . "\r\n"
         . $html;

    $s($msg . "\r\n.");
    $s("QUIT");
    fclose($smtp);
    return true;
}
