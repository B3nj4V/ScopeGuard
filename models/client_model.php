<?php
declare(strict_types=1);

class client_model {
    public function __construct(private PDO $db) {}

    public function create(array $d): int {
        $st=$this->db->prepare("INSERT INTO clients(name,vat,website,phone,currency,default_tax,address_json,created_at)
      VALUES(?,?,?,?,?,?,?,?)");
        $st->execute([
            trim($d['name']??''),
            $d['vat']??null,
            $d['website']??null,
            $d['phone']??null,
            $d['currency']??'USD',
            (float)($d['default_tax']??0),
            json_encode($d['address']??[], JSON_UNESCAPED_UNICODE),
            time()
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $d): void {
        $st=$this->db->prepare("UPDATE clients SET name=?, vat=?, website=?, phone=?, currency=?, default_tax=?, address_json=? WHERE id=?");
        $st->execute([
            trim($d['name']??''),
            $d['vat']??null,
            $d['website']??null,
            $d['phone']??null,
            $d['currency']??'USD',
            (float)($d['default_tax']??0),
            json_encode($d['address']??[], JSON_UNESCAPED_UNICODE),
            $id
        ]);
    }

    public function find(int $id): ?array {
        $st=$this->db->prepare("SELECT * FROM clients WHERE id=?");
        $st->execute([$id]);
        $r=$st->fetch(PDO::FETCH_ASSOC);
        if(!$r) return null;
        $r['address'] = json_decode($r['address_json']??'{}',true) ?: [];
        return $r;
    }

    public function all_simple(): array {
        return $this->db->query("SELECT id,name FROM clients ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(string $q='', int $limit=50, int $offset=0): array {
        $params=[]; $where='1=1';
        if($q!==''){
            $where.=" AND (name LIKE :q OR IFNULL(vat,'') LIKE :q)";
            $params[':q']='%'.$q.'%';
        }
        $st=$this->db->prepare("SELECT * FROM clients WHERE $where ORDER BY id DESC LIMIT :lim OFFSET :off");
        foreach($params as $k=>$v){ $st->bindValue($k,$v,PDO::PARAM_STR); }
        $st->bindValue(':lim',$limit,PDO::PARAM_INT);
        $st->bindValue(':off',$offset,PDO::PARAM_INT);
        $st->execute();
        $rows=$st->fetchAll(PDO::FETCH_ASSOC);

        $tc=$this->db->prepare("SELECT COUNT(*) FROM clients WHERE $where");
        foreach($params as $k=>$v){ $tc->bindValue($k,$v,PDO::PARAM_STR); }
        $tc->execute();
        $total=(int)$tc->fetchColumn();

        return ['rows'=>$rows,'total'=>$total];
    }
}
