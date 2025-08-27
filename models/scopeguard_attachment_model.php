<?php
class scopeguard_attachment_model {
    private PDO $pdo; public function __construct(PDO $pdo){ $this->pdo=$pdo; }
    public function list(int $crId): array {
        $st=$this->pdo->prepare("SELECT * FROM scopeguard_attachments WHERE change_request_id=? ORDER BY id DESC");
        $st->execute([$crId]); return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    public function add(int $crId,string $name,string $path,int $size): void {
        $st=$this->pdo->prepare("INSERT INTO scopeguard_attachments(change_request_id,filename,path,size,uploaded_at) VALUES (?,?,?,?,?)");
        $st->execute([$crId,$name,$path,$size,time()]);
    }
    public function delete(int $id): void {
        $this->pdo->prepare("DELETE FROM scopeguard_attachments WHERE id=?")->execute([$id]);
    }



}

