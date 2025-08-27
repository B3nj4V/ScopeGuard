<?php
class scopeguard_item_model {
    private PDO $pdo; public function __construct(PDO $pdo){ $this->pdo=$pdo; }
    public function list(int $crId): array {
        $st=$this->pdo->prepare("SELECT * FROM scopeguard_items WHERE change_request_id=? ORDER BY sort,id");
        $st->execute([$crId]); return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    public function add(int $crId, array $d): int {
        $st=$this->pdo->prepare("INSERT INTO scopeguard_items(change_request_id,name,qty,unit_price,hours,tax_rate,discount,sort)
      VALUES (?,?,?,?,?,?,?,?)");
        $st->execute([$crId,$d['name'],(float)$d['qty'],(float)$d['unit_price'],(float)$d['hours'],(float)$d['tax_rate'],(float)$d['discount'],(int)$d['sort']]);
        return (int)$this->pdo->lastInsertId();
    }
    public function delete(int $id): void {
        $this->pdo->prepare("DELETE FROM scopeguard_items WHERE id=?")->execute([$id]);
    }
}

