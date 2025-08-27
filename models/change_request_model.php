<?php
// ScopeGuard/models/change_request_model.php
class change_request_model {
    private PDO $pdo;
    public function __construct(PDO $pdo){ $this->pdo=$pdo; }

    public function all(): array {
        return $this->pdo->query("SELECT * FROM scopeguard_change_requests ORDER BY id DESC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }
    public function find(int $id): ?array {
        $st=$this->pdo->prepare("SELECT * FROM scopeguard_change_requests WHERE id=?");
        $st->execute([$id]); $r=$st->fetch(PDO::FETCH_ASSOC); return $r?:null;
    }
    public function findByToken(string $token): ?array {
        $st=$this->pdo->prepare("SELECT * FROM scopeguard_change_requests WHERE public_token=?");
        $st->execute([$token]); $r=$st->fetch(PDO::FETCH_ASSOC); return $r?:null;
    }
    public function create(array $d): int {
        $st=$this->pdo->prepare("INSERT INTO scopeguard_change_requests
      (title,description,currency,cost_delta,time_delta_hours,status,created_at)
      VALUES (?,?,?,?,?,'draft',?)");
        $st->execute([$d['title'],$d['description'],$d['currency'],$d['cost_delta'],$d['time_delta_hours'],time()]);
        return (int)$this->pdo->lastInsertId();
    }
    public function setToken(int $id,string $token,int $expires): void {
        $st=$this->pdo->prepare("UPDATE scopeguard_change_requests
      SET public_token=?, token_expires_at=?, status='sent', sent_at=? WHERE id=?");
        $st->execute([$token,$expires,time(),$id]);
    }
    public function approve(int $id): void {
        $st=$this->pdo->prepare("UPDATE scopeguard_change_requests SET status='approved', approved_at=? WHERE id=?");
        $st->execute([time(),$id]);
    }
    public function reject(int $id): void {
        $st=$this->pdo->prepare("UPDATE scopeguard_change_requests SET status='rejected', rejected_at=? WHERE id=?");
        $st->execute([time(),$id]);
    }
}
