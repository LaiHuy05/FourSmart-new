<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

final class PasswordRecoveryMailer
{
    public function __construct(private string $appUrl)
    {
    }

    public function send(string $email, #[SensitiveParameter] string $token): void
    {
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = '127.0.0.1';
        $mail->Port = 1025;
        $mail->SMTPAuth = false;
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;
        $mail->SMTPDebug = 0;
        $mail->Timeout = 5;
        $mail->getSMTPInstance()->Timelimit = 10;
        $mail->Hostname = 'localhost';
        $mail->Helo = 'localhost';
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom('noreply@foursmart.test', 'FourSmart');
        $mail->addAddress($email);
        $mail->Subject = 'FourSmart - Đặt lại mật khẩu';
        $mail->isHTML(false);
        // Fragment is never sent in the HTTP request line or Referer header.
        $mail->Body = "Bạn vừa yêu cầu đặt lại mật khẩu Client FourSmart.\n\n"
            . $this->appUrl . '?client=resetPassword#token=' . $token
            . "\n\nLiên kết có hiệu lực 15 phút và chỉ dùng một lần.\n"
            . "Nếu bạn không yêu cầu, hãy bỏ qua email này. Không chia sẻ liên kết.\n";
        $mail->send();
    }
}
