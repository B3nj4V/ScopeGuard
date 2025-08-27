<?php
// models/scopeguard_approval_model.php
class scopeguard_approval_model {
    private PDO $pdo;
    public function __construct(PDO $pdo){ $this->pdo = $pdo; }

    public function ensure_defaults(int $crId): void {
        foreach (['client','pm','legal'] as $role) {
            $st = $this->pdo->prepare("SELECT 1 FROM scopeguard_approvals WHERE change_request_id=? AND role=?");
            $st->execute([$crId,$role]);
            if (!$st->fetchColumn()) {
                $ins = $this->pdo->prepare("INSERT INTO scopeguard_approvals (change_request_id, role, status) VALUES (?,?, 'pending')");
                $ins->execute([$crId,$role]);
            }
        }
    }

    public function list_by_cr(int $crId): array {
        $st = $this->pdo->prepare("SELECT * FROM scopeguard_approvals WHERE change_request_id=? ORDER BY role");
        $st->execute([$crId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get_by_token(string $token): ?array {
        $st = $this->pdo->prepare("SELECT * FROM scopeguard_approvals WHERE token=? LIMIT 1");
        $st->execute([$token]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function get_by_cr_role(int $crId, string $role): ?array {
        $st = $this->pdo->prepare("SELECT * FROM scopeguard_approvals WHERE change_request_id=? AND role=? LIMIT 1");
        $st->execute([$crId,$role]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }
    public function update_contact(int $apprId, string $name = '', string $email = ''): void {
        $name = trim($name);
        $email = trim($email);
        if($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)){
            // si el email no es válido, lo guardamos vacío para no romper notificaciones
            $email = '';
        }
        $st = $this->pdo->prepare("UPDATE scopeguard_approvals SET approver_name=?, approver_email=?, updated_at=? WHERE id=?");
        $st->execute([$name, $email, time(), $apprId]);
    }
    public function issue_token(int $id, string $token, int $expires): void {

        $st = $this->pdo->prepare("UPDATE scopeguard_approvals SET token=?, token_expires_at=?, last_sent_at=? WHERE id=?");
        $st->execute([$token,$expires,time(),$id]);
    }

    public function approve(int $id): void {
        $st = $this->pdo->prepare("UPDATE scopeguard_approvals SET status='approved', acted_at=? WHERE id=?");
        $st->execute([time(),$id]);
    }

    public function reject(int $id): void {
        $st = $this->pdo->prepare("UPDATE scopeguard_approvals SET status='rejected', acted_at=? WHERE id=?");
        $st->execute([time(),$id]);
    }
}
