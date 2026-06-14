<?php
/**
 * Simple SMTP Mailer Class
 * Handles basic SMTP authentication and mail sending.
 */
class SmtpMailer {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $secure;
    private $from_email;
    private $from_name;

    public function __construct($config) {
        $this->host = $config['smtp_host'];
        $this->port = $config['smtp_port'];
        $this->user = $config['smtp_user'];
        $this->pass = $config['smtp_pass'];
        $this->secure = $config['smtp_secure'];
        $this->from_email = $config['from_email'];
        $this->from_name = $config['from_name'];
    }

    public function send($to, $subject, $message, $cc = [], $attachments = []) {
        return $this->socketSend($to, $subject, $message, $cc, $attachments);
    }

    private function socketSend($to, $subject, $message, $cc = [], $attachments = []) {
        $timeout = 30;
        $newline = "\r\n";
        
        $cafile = __DIR__ . '/cacert.pem';
        $ssl_options = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ];
        
        if (file_exists($cafile)) {
            $ssl_options['cafile'] = $cafile;
        } else {
            $ssl_options['verify_peer'] = false;
            $ssl_options['verify_peer_name'] = false;
        }

        $context = stream_context_create(['ssl' => $ssl_options]);
        
        $remote_host = ($this->secure === 'ssl' ? 'ssl://' : 'tcp://') . $this->host;
        $socket = stream_socket_client($remote_host . ":" . $this->port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        
        if (!$socket) {
            error_log("SMTP Connection Error: $errstr ($errno)");
            return false;
        }
        stream_set_timeout($socket, 10);

        $this->getResponse($socket);
        
        fwrite($socket, "EHLO " . $this->host . $newline);
        $this->getResponse($socket);

        if ($this->secure === 'tls') {
            fwrite($socket, "STARTTLS" . $newline);
            $this->getResponse($socket);
            
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("SMTP TLS Handshake Failed on " . $remote_host . ":" . $this->port);
                return false;
            }
            fwrite($socket, "EHLO " . $this->host . $newline);
            $this->getResponse($socket);
        }

        fwrite($socket, "AUTH LOGIN" . $newline);
        $this->getResponse($socket);

        fwrite($socket, base64_encode($this->user) . $newline);
        $this->getResponse($socket);

        fwrite($socket, base64_encode($this->pass) . $newline);
        $this->getResponse($socket);

        fwrite($socket, "MAIL FROM: <$this->from_email>" . $newline);
        $this->getResponse($socket);

        fwrite($socket, "RCPT TO: <$to>" . $newline);
        $this->getResponse($socket);

        foreach ($cc as $cc_email) {
            fwrite($socket, "RCPT TO: <$cc_email>" . $newline);
            $this->getResponse($socket);
        }

        fwrite($socket, "DATA" . $newline);
        $this->getResponse($socket);

        $boundary = md5(time());

        $headers = "From: " . $this->from_name . " <" . $this->from_email . ">" . $newline;
        $headers .= "To: <$to>" . $newline;
        if (!empty($cc)) {
            $headers .= "Cc: " . implode(', ', $cc) . $newline;
        }
        $headers .= "Subject: $subject" . $newline;
        $headers .= "MIME-Version: 1.0" . $newline;
        
        fwrite($socket, $headers);

        if (!empty($attachments)) {
            fwrite($socket, "Content-Type: multipart/mixed; boundary=\"$boundary\"" . $newline . $newline);
            
            fwrite($socket, "--$boundary" . $newline);
            fwrite($socket, "Content-Type: text/html; charset=utf-8" . $newline);
            fwrite($socket, "Content-Transfer-Encoding: 8bit" . $newline . $newline);
            fwrite($socket, $message . $newline . $newline);
            
            foreach ($attachments as $att) {
                $file_name = $att['name'];
                $file_path = $att['path'];
                if (file_exists($file_path)) {
                    fwrite($socket, "--$boundary" . $newline);
                    fwrite($socket, "Content-Type: application/octet-stream; name=\"$file_name\"" . $newline);
                    fwrite($socket, "Content-Transfer-Encoding: base64" . $newline);
                    fwrite($socket, "Content-Disposition: attachment; filename=\"$file_name\"" . $newline . $newline);
                    
                    $fp = fopen($file_path, "rb");
                    if ($fp) {
                        while (!feof($fp)) {
                            $chunk = fread($fp, 57);
                            if ($chunk !== false && strlen($chunk) > 0) {
                                fwrite($socket, base64_encode($chunk) . $newline);
                            }
                        }
                        fclose($fp);
                    }
                    fwrite($socket, $newline);
                }
            }
            fwrite($socket, "--$boundary--" . $newline);
        } else {
            fwrite($socket, "Content-Type: text/html; charset=utf-8" . $newline);
            fwrite($socket, "Content-Transfer-Encoding: 8bit" . $newline . $newline);
            fwrite($socket, $message . $newline);
        }

        fwrite($socket, "." . $newline);
        $this->getResponse($socket);

        fwrite($socket, "QUIT" . $newline);
        fclose($socket);

        return true;
    }

    private function getResponse($socket) {
        $response = "";
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            // echo "DEBUG: $str";
            if (substr($str, 3, 1) == " ") break;
        }
        $info = stream_get_meta_data($socket);
        return $response;
    }
}
?>
