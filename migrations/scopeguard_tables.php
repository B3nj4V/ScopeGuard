<?php
// ScopeGuard/migrations/scopeguard_tables.php
function scopeguard_migrate(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS scopeguard_change_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    currency TEXT DEFAULT 'USD',
    cost_delta REAL DEFAULT 0,
    time_delta_hours INTEGER DEFAULT 0,
    status TEXT DEFAULT 'draft',
    version INTEGER DEFAULT 1,
    public_token TEXT,
    token_expires_at INTEGER,
    created_at INTEGER,
    updated_at INTEGER,
    sent_at INTEGER,
    approved_at INTEGER,
    rejected_at INTEGER
            
 
  )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS scopeguard_signatures (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    change_request_id INTEGER NOT NULL,
    signer_type TEXT NOT NULL,
    signer_name TEXT,
    signer_email TEXT,
    action TEXT NOT NULL,
    signed_at INTEGER,
    ip TEXT,
    user_agent TEXT
  )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS scopeguard_audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    change_request_id INTEGER NOT NULL,
    event TEXT NOT NULL,
    meta TEXT,
    created_at INTEGER,
    staff_id INTEGER
  )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS scopeguard_approvals (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  change_request_id INTEGER NOT NULL,
  role TEXT NOT NULL,                -- 'client', 'pm', 'legal'
  approver_name TEXT,
  approver_email TEXT,
  status TEXT DEFAULT 'pending',     -- 'pending','approved','rejected'
  token TEXT,
  token_expires_at INTEGER,
  acted_at INTEGER,
  last_sent_at INTEGER,
  UNIQUE(change_request_id, role)
)");


    $pdo->exec("CREATE TABLE IF NOT EXISTS scopeguard_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  change_request_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  qty REAL DEFAULT 1,
  unit_price REAL DEFAULT 0,
  hours REAL DEFAULT 0,
  tax_rate REAL DEFAULT 0,  -- % IVA
  discount REAL DEFAULT 0,  -- % descuento
  sort INT DEFAULT 0

                                            
                                            
                                            )");

// === USERS
    $pdo->exec("CREATE TABLE IF NOT EXISTS scopeguard_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  role TEXT NOT NULL DEFAULT 'staff',
  password_hash TEXT NOT NULL,
  notify_prefs TEXT NULL,
  avatar_path TEXT NULL,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL
)");

    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM scopeguard_users")->fetchColumn();
    if($cnt===0){
        // Admin de arranque (CAMBIA la clave tras ingresar)
        $name='Admin';
        $email='admin@local';
        $role='admin';
        $pass='admin123';
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $now=time();
        $st=$pdo->prepare("INSERT INTO scopeguard_users(name,email,role,password_hash,notify_prefs,created_at,updated_at)
                     VALUES (?,?,?,?,?,?,?)");
        $st->execute([$name,$email,$role,$hash,'{}',$now,$now]);
    }

}
