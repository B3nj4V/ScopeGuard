<?php
// ScopeGuard/models/scopeguard_audit_model.php
class scopeguard_audit_model {
    private PDO $pdo;
    public function __construct(PDO $pdo){ $this->pdo=$pdo; }
    public function log(int $crId,string $event,array $meta=[],?int $staffId=null): void {
        $st=$this->pdo->prepare("INSERT INTO scopeguard_audit_log
      (change_request_id,event,meta,created_at,staff_id) VALUES (?,?,?,?,?)");
        $st->execute([$crId,$event,json_encode($meta),time(),$staffId]);
    }
}

