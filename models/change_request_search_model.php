<?php
// models/change_request_search_model.php

class change_request_search_model {
    private PDO $pdo;
    private array $allowed_status = ['draft','sent','approved','rejected'];

    public function __construct(PDO $pdo){ $this->pdo = $pdo; }

    public function normalize_filters(array $in): array {
        $q = trim((string)($in['q'] ?? ''));
        $status = trim((string)($in['status'] ?? ''));
        if(!in_array($status, $this->allowed_status, true)) $status = '';

        $date_from = trim((string)($in['date_from'] ?? ''));
        $date_to   = trim((string)($in['date_to'] ?? ''));
        $ts_from = $this->to_ts_start($date_from);
        $ts_to   = $this->to_ts_end_exclusive($date_to);

        $amount_min = ($in['amount_min'] ?? '') !== '' ? (float)$in['amount_min'] : null;
        $amount_max = ($in['amount_max'] ?? '') !== '' ? (float)$in['amount_max'] : null;

        $page = max(1, (int)($in['p'] ?? 1));
        $per  = (int)($in['per'] ?? 10);
        if(!in_array($per, [10,25,50], true)) $per = 10;

        return [
            'q' => $q,
            'status' => $status,
            'ts_from' => $ts_from,
            'ts_to' => $ts_to,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'amount_min' => $amount_min,
            'amount_max' => $amount_max,
            'page' => $page,
            'per' => $per,
        ];
    }

    private function to_ts_start(string $ymd): ?int {
        if($ymd==='') return null;
        $ts = strtotime($ymd.' 00:00:00');
        return $ts ?: null;
    }

    private function to_ts_end_exclusive(string $ymd): ?int {
        if($ymd==='') return null;
        // usamos "< ts_end_exclusive" => sumamos 1 día
        $ts = strtotime($ymd.' 00:00:00');
        return $ts ? ($ts + 86400) : null;
    }

    private function build_where(array $f): array {
        $w=[]; $p=[];
        if($f['status']!==''){ $w[]='status = ?'; $p[]=$f['status']; }
        if($f['q']!==''){ $w[]='(title LIKE ? OR description LIKE ?)'; $p[]='%'.$f['q'].'%'; $p[]='%'.$f['q'].'%'; }
        if($f['ts_from']!==null){ $w[]='created_at >= ?'; $p[]=(int)$f['ts_from']; }
        if($f['ts_to']!==null){ $w[]='created_at < ?';  $p[]=(int)$f['ts_to']; }
        if($f['amount_min']!==null){ $w[]='cost_delta >= ?'; $p[]=(float)$f['amount_min']; }
        if($f['amount_max']!==null){ $w[]='cost_delta <= ?'; $p[]=(float)$f['amount_max']; }

        $where = $w ? (' WHERE '.implode(' AND ', $w)) : '';
        return [$where, $p];
    }

    public function search(array $filters): array {
        $f = $this->normalize_filters($filters);
        [$where, $params] = $this->build_where($f);

        // total
        $sqlCount = "SELECT COUNT(*) AS c FROM scopeguard_change_requests".$where;
        $st = $this->pdo->prepare($sqlCount);
        $st->execute($params);
        $total = (int)$st->fetchColumn();

        // datos paginados
        $order = " ORDER BY created_at DESC, id DESC";
        $limit = (int)$f['per'];
        $offset = (int)(($f['page']-1)*$f['per']);
        // SQLite acepta LIMIT/OFFSET numéricos embebidos
        $sqlRows = "SELECT id,title,status,currency,cost_delta,time_delta_hours,public_token,created_at
                FROM scopeguard_change_requests".$where.$order." LIMIT ".$limit." OFFSET ".$offset;
        $st2 = $this->pdo->prepare($sqlRows);
        $st2->execute($params);
        $rows = $st2->fetchAll(PDO::FETCH_ASSOC);

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $f['page'],
            'per' => $f['per'],
            'filters' => $f,
            'pages' => max(1, (int)ceil($total / max(1,$f['per']))),
        ];
    }
}
