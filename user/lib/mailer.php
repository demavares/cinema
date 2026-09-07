<?php
// ============================================
// CORREO (SMTP) — Helper de envío con PHPMailer
// Usa las variables MAIL_* del .env (ver config.php)
// ============================================

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Envía un correo usando SMTP configurado en .env
 *
 * @param string $to      Destinatario (email)
 * @param string $toName  Nombre del destinatario
 * @param string $subject Asunto
 * @param string $html    Cuerpo HTML
 * @param string $alt     Cuerpo texto plano (alternativo)
 * @return array ['ok' => bool, 'error' => string]
 */
function sendAppMail(string $to, string $toName, string $subject, string $html, string $alt = ''): array
{
    $host = env('MAIL_HOST', '');
    $port = (int) env('MAIL_PORT', 587);
    $secure = strtolower((string) env('MAIL_SECURE', 'tls'));
    $smtpAuth = filter_var(env('MAIL_SMTP_AUTH', true), FILTER_VALIDATE_BOOL);
    $username = (string) env('MAIL_USER', '');
    $password = (string) env('MAIL_PASS', '');
    $from = (string) env('MAIL_FROM', '');
    $fromName = (string) env('MAIL_FROM_NAME', '');

    date_default_timezone_set('America/Caracas');

    if ($host === '' || $username === '' || $password === '' || $from === '') {
        return [
            'ok' => false,
            'error' => 'Configuración SMTP incompleta. Revisa las variables MAIL_* en el archivo .env.'
        ];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = $smtpAuth;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->Port = $port;
        $mail->SMTPSecure = ($secure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->setLanguage('es', __DIR__ . '/phpmailer/language/');
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = ($alt !== '') ? $alt : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));
        $mail->send();

        return ['ok' => true, 'error' => ''];
    } catch (PHPMailerException $e) {
        error_log("❌ Error PHPMailer: " . $e->getMessage());
        return ['ok' => false, 'error' => 'Error al enviar el correo: ' . $e->getMessage()];
    } catch (Throwable $e) {
        error_log("❌ Error envío de correo: " . $e->getMessage());
        return ['ok' => false, 'error' => 'Error inesperado al enviar el correo.'];
    }
}