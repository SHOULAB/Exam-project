<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

// ─── SMTP Configuration ───────────────────────────────────────────────────────
// Fill in your SMTP credentials below.
// For Gmail: enable 2FA and generate an App Password at https://myaccount.google.com/apppasswords
define('SMTP_HOST',      'smtp.gmail.com');      // SMTP server
define('SMTP_PORT',      587);                   // 587 = STARTTLS, 465 = SSL
define('SMTP_USERNAME',  'budgetarinfo@gmail.com');   // Your SMTP login / email address
define('SMTP_PASSWORD',  'iisw ejde gdvv whpz');          // App password (not your account password)
define('SMTP_FROM',      'budgetarinfo@gmail.com');   // From address (usually same as username)
define('SMTP_FROM_NAME', 'Budgetar');            // From display name

/**
 * Send a password-reset email.
 *
 * @param string $to_email   Recipient email address
 * @param string $to_name    Recipient display name
 * @param string $reset_link Full URL of the reset page with token
 * @return bool True on success, false on failure
 */
function send_reset_email(string $to_email, string $to_name, string $reset_link): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = 'Paroles atjaunošana - Budgetar';

        $safe_name = htmlspecialchars($to_name, ENT_QUOTES, 'UTF-8');
        $safe_link = htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8');

        $mail->Body = '<!DOCTYPE html>
<html lang="lv">
<head><meta charset="UTF-8"></head>
<body style="font-family:sans-serif;background:#f4f4f4;margin:0;padding:20px;">
  <div style="max-width:520px;margin:auto;background:#fff;border-radius:8px;padding:36px;">
    <h2 style="margin-top:0;color:#1a1a2e;">Paroles atjaunošana</h2>
    <p>Sveiki, <strong>' . $safe_name . '</strong>!</p>
    <p>Saņēmām pieprasījumu atjaunot Jūsu <strong>Budgetar</strong> konta paroli.</p>
    <p>Lai iestatītu jaunu paroli, noklikšķiniet uz pogas zemāk.<br>
       Saite ir derīga <strong>1 stundu</strong>.</p>
    <p style="text-align:center;margin:28px 0;">
      <a href="' . $safe_link . '"
         style="background:#6c63ff;color:#fff;padding:13px 28px;border-radius:6px;
                text-decoration:none;font-weight:bold;display:inline-block;">
        Atjaunot paroli
      </a>
    </p>
    <p style="color:#888;font-size:13px;">
      Ja Jūs šo pieprasījumu neveicāt, varat šo e-pastu ignorēt —
      Jūsu parole netiks mainīta.
    </p>
    <hr style="border:none;border-top:1px solid #eee;margin:20px 0;">
    <p style="color:#aaa;font-size:12px;margin:0;">
      Vai arī kopējiet šo saiti pārlūkprogrammā:<br>
      <a href="' . $safe_link . '" style="color:#6c63ff;">' . $safe_link . '</a>
    </p>
  </div>
</body>
</html>';

        $mail->AltBody = "Paroles atjaunošana\n\n"
            . "Sveiki, {$to_name}!\n\n"
            . "Atjaunojiet savu Budgetar paroli, apmeklējot šo saiti:\n{$reset_link}\n\n"
            . "Saite ir derīga 1 stundu.\n\n"
            . "Ja Jūs šo pieprasījumu neveicāt, ignorējiet šo e-pastu.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
