<?php
declare(strict_types=1);

function clients_migrate(PDO $pdo): void {
    // clients
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    vat TEXT,
    website TEXT,
    phone TEXT,
    currency TEXT DEFAULT 'USD',
    default_tax REAL DEFAULT 0,
    address_json TEXT,
    created_at INTEGER
  )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clients_name ON clients(name)");

    // client_contacts
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_contacts(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    name TEXT,
    email TEXT,
    phone TEXT,
    is_primary INTEGER DEFAULT 0,
    portal_enabled INTEGER DEFAULT 0,
    password_hash TEXT,
    created_at INTEGER,
    FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE
  )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_client_contacts_client ON client_contacts(client_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_client_contacts_email ON client_contacts(email)");
}

/** Añade client_id y project_id a scopeguard_change_requests si no existen */
function scopeguard_alter_add_client_fields(PDO $pdo): void {
    $cols = $pdo->query("PRAGMA table_info(scopeguard_change_requests)")->fetchAll(PDO::FETCH_ASSOC);
    $have = [];
    foreach($cols as $c){ $have[$c['name']] = true; }
    if (empty($have['client_id'])) {
        $pdo->exec("ALTER TABLE scopeguard_change_requests ADD COLUMN client_id INTEGER NULL");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cr_client_id ON scopeguard_change_requests(client_id)");
    }
    if (empty($have['project_id'])) {
        $pdo->exec("ALTER TABLE scopeguard_change_requests ADD COLUMN project_id INTEGER NULL");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cr_project_id ON scopeguard_change_requests(project_id)");
    }
}

