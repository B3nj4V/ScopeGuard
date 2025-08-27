<?php
// ScopeGuard/scopeguard/scopeguard_token.php
class scopeguard_token {
    private string $secret; private int $ttl;
    public function __construct(array $cfg){ $this->secret=$cfg['secret']; $this->ttl=(int)($cfg['token_ttl_minutes']??(60*24*7)); }
    public function issue(array $payload, ?int $minutes=null): string {
        $exp = time()+60*($minutes??$this->ttl);
        $body=rtrim(strtr(base64_encode(json_encode(['exp'=>$exp,'p'=>$payload])), '+/','-_'),'=');
        $sigb=rtrim(strtr(base64_encode(hash_hmac('sha256',$body,$this->secret,true)),'+/','-_'),'=');
        return $body.'.'.$sigb;
    }
    public function verify(string $t): ?array {
        $p=explode('.',$t); if(count($p)!==2) return null; [$b,$s]=$p;
        $c=rtrim(strtr(base64_encode(hash_hmac('sha256',$b,$this->secret,true)),'+/','-_'),'=');
        if(!hash_equals($c,$s)) return null;
        $d=json_decode(base64_decode(strtr($b,'-_','+/')),true);
        return ($d && ($d['exp']??0)>=time()) ? ($d['p']??null) : null;
    }
}

