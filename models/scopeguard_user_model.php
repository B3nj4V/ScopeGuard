<?php
// models/scopeguard_user_model.php
class scopeguard_user_model {
    private PDO $pdo;
    public function __construct(PDO $pdo){ $this->pdo = $pdo; }

    public function find_by_id(int $id): ?array {
        $st=$this->pdo->prepare("SELECT * FROM scopeguard_users WHERE id=?");
        $st->execute([$id]);
        $r=$st->fetch(PDO::FETCH_ASSOC);
        return $r?:null;
    }

    public function find_by_email(string $email): ?array {
        $st=$this->pdo->prepare("SELECT * FROM scopeguard_users WHERE email=?");
        $st->execute([trim($email)]);
        $r=$st->fetch(PDO::FETCH_ASSOC);
        return $r?:null;
    }

    public function count_users(): int {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM scopeguard_users")->fetchColumn();
    }

    public function create(string $name, string $email, string $password, string $role='staff'): int {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $now=time();
        $st=$this->pdo->prepare("INSERT INTO scopeguard_users(name,email,role,password_hash,notify_prefs,created_at,updated_at)
                             VALUES (?,?,?,?,?,?,?)");
        $st->execute([$name, trim($email), $role, $hash, '{}', $now, $now]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update_profile(int $id, string $name, string $email, string $role='staff', array $notify=[]): void {
        $now=time();
        $prefs=json_encode($notify, JSON_UNESCAPED_UNICODE);
        $st=$this->pdo->prepare("UPDATE scopeguard_users SET name=?, email=?, role=?, notify_prefs=?, updated_at=? WHERE id=?");
        $st->execute([trim($name), trim($email), $role, $prefs, $now, $id]);
    }

    public function update_password(int $id, string $newPassword): void {
        $hash=password_hash($newPassword, PASSWORD_DEFAULT);
        $st=$this->pdo->prepare("UPDATE scopeguard_users SET password_hash=?, updated_at=? WHERE id=?");
        $st->execute([$hash, time(), $id]);
    }
}
