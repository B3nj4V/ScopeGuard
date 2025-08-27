<?php
// scopeguard/scopeguard_auth.php
require_once __DIR__.'/../models/scopeguard_user_model.php';

function sg_auth_bootstrap_start_session(): void {
    if(session_status() === PHP_SESSION_NONE){
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off');
        session_name('SGSESSID');
        session_set_cookie_params([
            'lifetime'=>0,
            'path'=>'/',
            'domain'=>'',
            'secure'=>$secure,
            'httponly'=>true,
            'samesite'=>'Lax'
        ]);
        session_start();
    }
}

class scopeguard_auth {
    private PDO $pdo;
    private scopeguard_user_model $um;
    public function __construct(PDO $pdo){
        $this->pdo=$pdo;
        $this->um=new scopeguard_user_model($pdo);
    }
    public function login(string $email, string $password): bool {
        $u=$this->um->find_by_email(trim($email));
        if(!$u) return false;
        if(!password_verify($password, $u['password_hash'])) return false;
        $_SESSION['uid']=(int)$u['id'];
        return true;
    }
    public function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
    public function is_logged(): bool { return !empty($_SESSION['uid']); }
    public function user(): ?array {
        if(empty($_SESSION['uid'])) return null;
        return $this->um->find_by_id((int)$_SESSION['uid']);
    }
}
