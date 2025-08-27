<?php
// models/scopeguard_mailer.php
class scopeguard_mailer {
    private array $cfg;
    public function __construct(array $cfg){ $this->cfg = $cfg; }

    /**
     * @param string|array $to
     */
    public function send($to, string $subject, string $html, ?string $text = null): bool {
        $toList = is_array($to) ? $to : [$to];
        $toList = array_values(array_filter(array_map('trim', $toList)));
        if(empty($toList)) return false;

        $from = $this->cfg['mail']['from'] ?? 'no-reply@localhost';
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: '.$from;
        if(!empty($this->cfg['mail']['reply_to'])){
            $headers[] = 'Reply-To: '.$this->cfg['mail']['reply_to'];
        }

        $okAll = true;
        $html = $this->wrap_html($html);

        foreach($toList as $rcpt){
            $ok = @mail($rcpt, '=?UTF-8?B?'.base64_encode($subject).'?=', $html, implode("\r\n",$headers));
            if(!$ok) $okAll = false;
        }
        return $okAll;
    }

    private function wrap_html(string $inner): string {
        return '<!doctype html><html><head><meta charset="utf-8" /></head><body style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#111">'.
            $inner.'</body></html>';
    }
}
