<?php
declare(strict_types=1);

class scopeguard_settings_model {
    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
        $this->ensure_table();
    }

    private function ensure_table(): void {
        $this->pdo->exec("
      CREATE TABLE IF NOT EXISTS scopeguard_settings(
        key TEXT PRIMARY KEY,
        value TEXT
      )
    ");
    }

    /** Obtiene un valor de ajustes (string) o $default si no existe */
    public function get(string $key, $default=null){
        $st = $this->pdo->prepare("SELECT value FROM scopeguard_settings WHERE key=?");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return ($v===false) ? $default : $v;
    }

    /** Guarda múltiples pares clave=>valor (stringificará cualquier tipo simple) */
    public function set_many(array $kv): void {
        $this->pdo->beginTransaction();
        $st = $this->pdo->prepare("REPLACE INTO scopeguard_settings (key,value) VALUES (?,?)");
        foreach($kv as $k=>$v){
            if(is_array($v) || is_object($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            $st->execute([$k, (string)$v]);
        }
        $this->pdo->commit();
    }

    /** Retorna todos los ajustes como array asociativo */
    public function all(): array {
        $rows = $this->pdo->query("SELECT key,value FROM scopeguard_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        return $rows ?: [];
    }
}
