<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php'; // Composer autoload

function sendVerificationEmail($email, $name, $code) {
    $mail = new PHPMailer(true);

    try {
        // SMTP SETTINGS
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'queenhezekiah04@gmail.com';
        $mail->Password   = 'xqie zwgg hqwr goik'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // SENDER / RECEIVER
        $mail->setFrom('queenhezekiah04@gmail.com', 'eTOUR Verification');
        $mail->addAddress($email, $name);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = "Your eTOUR Verification Code";

        $mail->Body = "
            <h2>Your Verification Code</h2>
            <p>Hello <strong>$name</strong>,</p>
            <p>Your verification code is:</p>
            <h1 style='color:#2b7a78; letter-spacing:5px;'>$code</h1>
            <p>Enter this code in the verification page to activate your account.</p>
        ";

        return $mail->send();

    } catch (Exception $e) {
        return false;
    }
}

function sendPasswordResetCode($email, $name, $code) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'queenhezekiah04@gmail.com';
        $mail->Password   = 'xqie zwgg hqwr goik'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('queenhezekiah04@gmail.com', 'eTOUR Password Reset');
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = "Your eTOUR Password Reset Code";
        $mail->Body = "
            <h2>Password Reset Code</h2>
            <p>Hello <strong>$name</strong>,</p>
            <p>Your password reset code is:</p>
            <h1 style='color:#2b7a78; letter-spacing:5px;'>$code</h1>
            <p>Enter this code to reset your password.</p>
            <p><strong>This code will expire in 15 minutes.</strong></p>
            <p>If you didn't request this, please ignore this email.</p>
        ";

        return $mail->send();
    } catch (Exception $e) {
        error_log('Password reset mail error: ' . $e->getMessage());
        return false;
    }
}
