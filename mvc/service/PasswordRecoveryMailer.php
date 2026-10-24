<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../config/password_recovery_mail.php';

final class PasswordRecoveryMailer
{
    public function __construct(private string $appUrl)
    {
    }

    public function send(#[SensitiveParameter] string $email, #[SensitiveParameter] string $token): void
    {
        // Fragment is never sent in the HTTP request line or Referer header.
        $url = $this->appUrl . '?client=resetPassword#token=' . rawurlencode($token);
        $text = "Bạn vừa yêu cầu đặt lại mật khẩu Client FourSmart.\n\n" . $url
            . "\n\nLiên kết có hiệu lực 15 phút và chỉ dùng một lần.\n"
            . "Nếu bạn không yêu cầu, hãy bỏ qua email này. Không chia sẻ liên kết.\n";
        $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html lang="vi"><head><meta charset="UTF-8"></head><body>'
            . '<p>Bạn vừa yêu cầu đặt lại mật khẩu Client FourSmart.</p>'
            . '<p><a href="' . $safeUrl . '">Đặt lại mật khẩu</a></p>'
            . '<p>Liên kết có hiệu lực 15 phút và chỉ dùng một lần.</p>'
            . '<p>Nếu bạn không yêu cầu, hãy bỏ qua email này. Không chia sẻ liên kết.</p></body></html>';
        $this->deliver($email, 'FourSmart - Đặt lại mật khẩu', $text, $html);
    }

    // No application bootstrap, database, job or recovery token is needed.
    public function sendTestMessage(): void
    {
        $text = "Xin chào! Đây là email kiểm thử tiếng Việt của FourSmart.\n"
            . "Nội dung UTF-8: Đặt lại mật khẩu, Nguyễn Văn An, ă â ê ô ơ ư đ.\n"
            . "Email chỉ kiểm tra Mailpit local; không chứa mã hoặc liên kết khôi phục.\n";
        $html = '<!doctype html><html lang="vi"><head><meta charset="UTF-8"></head><body>'
            . '<h1>Kiểm thử email FourSmart</h1><p>Xin chào! Đây là email kiểm thử tiếng Việt của FourSmart.</p>'
            . '<p>Nội dung UTF-8: Đặt lại mật khẩu, Nguyễn Văn An, ă â ê ô ơ ư đ.</p>'
            . '<p>Email chỉ kiểm tra Mailpit local; không chứa mã hoặc liên kết khôi phục.</p></body></html>';
        $this->deliver('developer@foursmart.test', 'FourSmart - Kiểm thử email tiếng Việt', $text, $html);
    }

    private function deliver(#[SensitiveParameter] string $email, string $subject,
        #[SensitiveParameter] string $text, #[SensitiveParameter] string $html): void
    {
        $mail = null;
        try {
            $config = PasswordRecoveryMailConfig::load();
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->Port = $config['port'];
            $mail->SMTPAuth = false;
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
            $mail->SMTPDebug = 0;
            $mail->Timeout = $config['timeout'];
            $mail->getSMTPInstance()->Timelimit = $config['timeout'];
            $mail->Hostname = 'localhost';
            $mail->Helo = 'localhost';
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($config['from'], $config['name']);
            $mail->addReplyTo($config['reply_to'], $config['name']);
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text;
            $mail->send();
        } catch (Throwable $error) {
            // Never chain SMTP exceptions: they may contain addresses or message data.
            throw new RuntimeException('Local email delivery failed.');
        } finally {
            if ($mail instanceof PHPMailer) {
                $mail->smtpClose();
            }
        }
    }
}
