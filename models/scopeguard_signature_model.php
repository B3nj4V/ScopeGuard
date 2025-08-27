<?php
// ScopeGuard/models/scopeguard_signature_model.php
class scopeguard_signature_model {
    private PDO $pdo;
    public function __construct(PDO $pdo){ $this->pdo=$pdo; }
    public function add(array $d): void {
        $st=$this->pdo->prepare("INSERT INTO scopeguard_signatures
      (change_request_id, signer_type, signer_name, signer_email, action, signed_at, ip, user_agent)
      VALUES (?,?,?,?,?,?,?,?)");
        $st->execute([
            $d['change_request_id'],$d['signer_type']??'client',$d['signer_name']??null,$d['signer_email']??null,
            $d['action'], time(), $d['ip']??null, $d['user_agent']??null
        ]);
    }
}
