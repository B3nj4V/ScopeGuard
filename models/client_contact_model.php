<?php
declare(strict_types=1);

class client_contact_model {
    public function __construct(private PDO $db) {}

    public function list_by_client(int $clientId): array {
        $st=$this->db->prepare("SELECT * FROM client_contacts WHERE client_id=? ORDER BY is_primary DESC, id DESC");
        $st->execute([$clientId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function primary_for_client(int $clientId): ?array {
        $st=$this->db->prepare("SELECT * FROM client_contacts WHERE client_id=? AND is_primary=1 LIMIT 1");
        $st->execute([$clientId]);
        $r=$st->fetch(PDO::FETCH_ASSOC);
        return $r?:null;
    }

    public function add(int $clientId, array $d): int {
        $isPrimary = !empty($d['is_primary']) ? 1 : 0;
        if($isPrimary){ $this->db->prepare("UPDATE client_contacts SET is_primary=0 WHERE client_id=?")->execute([$clientId]); }
        $st=$this->db->prepare("INSERT INTO client_contacts(client_id,name,email,phone,is_primary,portal_enabled,password_hash,created_at)
      VALUES(?,?,?,?,?,?,?,?)");
        $st->execute([
            $clientId,
            trim($d['name']??''),
            trim($d['email']??''),
            trim($d['phone']??''),
            $isPrimary,
            !empty($d['portal_enabled'])?1:0,
            !empty($d['password']) ? password_hash((string)$d['password'], PASSWORD_BCRYPT) : null,
            time()
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function set_primary(int $contactId): void {
        $cl = $this->db->prepare("SELECT client_id FROM client_contacts WHERE id=?");
        $cl->execute([$contactId]);
        $clientId=(int)$cl->fetchColumn();
        if($clientId>0){
            $this->db->prepare("UPDATE client_contacts SET is_primary=0 WHERE client_id=?")->execute([$clientId]);
            $this->db->prepare("UPDATE client_contacts SET is_primary=1 WHERE id=?")->execute([$contactId]);
        }
    }

    public function delete(int $contactId): void {
        $this->db->prepare("DELETE FROM client_contacts WHERE id=?")->execute([$contactId]);
    }
}
