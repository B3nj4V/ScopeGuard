<?php
// models/scopeguard_comment_model.php
class scopeguard_comment_model {
    private PDO $pdo;
    public function __construct(PDO $pdo){ $this->pdo = $pdo; }

    public function add(int $crId, string $body, string $author_type, string $author_name = '', string $visibility = 'internal'): int {
        $body = trim($body);
        if($body==='') return 0;
        if(!in_array($visibility,['internal','external'],true)) $visibility='internal';
        $now = time();
        $sql = "INSERT INTO scopeguard_comments(change_request_id, body, author_type, author_name, visibility, created_at)
            VALUES (?,?,?,?,?,?)";
        $st = $this->pdo->prepare($sql);
        $st->execute([$crId, $body, $author_type, $author_name, $visibility, $now]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Lista completa (admin). $only_external=true para mostrar solo visibles a cliente (público). */
    public function list(int $crId, bool $only_external = false): array {
        $sql = "SELECT id, change_request_id, body, author_type, author_name, visibility, created_at, notified_to
            FROM scopeguard_comments
            WHERE change_request_id = ?";
        if($only_external){
            $sql .= " AND visibility='external'";
        }
        $sql .= " ORDER BY created_at DESC, id DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute([$crId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Guarda a quién se notificó (CSV simple). */
    public function set_notified_to(int $commentId, array $emails): void {
        $csv = implode(',', array_unique(array_filter(array_map('trim',$emails))));
        $st = $this->pdo->prepare("UPDATE scopeguard_comments SET notified_to=? WHERE id=?");
        $st->execute([$csv, $commentId]);
    }
}
