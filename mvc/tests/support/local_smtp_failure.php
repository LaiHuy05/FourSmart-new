<?php

// Disposable loopback peer for transport failure tests. Never loads the application.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$server = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
if (!$server) { exit(1); }
echo stream_socket_get_name($server, false), "\n";
flush();
$peer = stream_socket_accept($server, 10);
if (!$peer) { exit(1); }
stream_set_timeout($peer, 5);
fwrite($peer, "220 localhost test SMTP\r\n");
$data = false;
while (($line = fgets($peer)) !== false) {
    if (($argv[1] ?? '') === 'timeout') { sleep(5); break; }
    if ($data) {
        if (rtrim($line) === '.') {
            $data = false;
            // Simulate an SMTP diagnostic which must never reach logs or callers.
            fwrite($peer, "554 private-smtp-diagnostic developer@foursmart.test\r\n");
            echo "DATA_REJECTED\n";
            flush();
        }
        continue;
    }
    if (str_starts_with($line, 'EHLO')) {
        fwrite($peer, "250-localhost\r\n250-STARTTLS\r\n250 AUTH PLAIN LOGIN\r\n");
    } elseif (str_starts_with($line, 'MAIL FROM:') || str_starts_with($line, 'RCPT TO:') || str_starts_with($line, 'RSET')) {
        fwrite($peer, "250 OK\r\n");
    } elseif (str_starts_with($line, 'DATA')) {
        $data = true;
        fwrite($peer, "354 send message\r\n");
    } elseif (str_starts_with($line, 'QUIT')) {
        fwrite($peer, "221 bye\r\n");
        break;
    } else {
        fwrite($peer, "554 unexpected-command\r\n");
    }
}
fclose($peer);
fclose($server);
