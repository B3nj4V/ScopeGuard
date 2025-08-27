<?php
// models/scopeguard_comment_service.php

require_once __DIR__.'/scopeguard_comment_model.php';
require_once __DIR__.'/scopeguard_mailer.php';
require_once __DIR__.'/scopeguard_approval_model.php'; // para leer emails de aprobadores

class scopeguard_comment_service {
    private PDO $pdo;
    private array $cfg;
    private scopeguard_comment_model $commentModel;
    private scopeguard_mailer $mailer;
    private scopeguard_approval_model $approvalModel;

    public function __construct(PDO $pdo, array $cfg){
        $this->pdo = $pdo;
        $this->cfg = $cfg;
        $this->commentModel   = new scopeguard_comment_model($pdo);
        $this->mailer         = new scopeguard_mailer($cfg);
        $this->approvalModel  = new scopeguard_approval_model($pdo);
    }

    /**
     * Crea comentario y dispara notificaciones según reglas:
     * - staff + external  => notificar a cliente(s) (aprobadores rol "Client")
     * - client (cualquier visibilidad) => notificar a staff (cfg notify.staff_emails + PM si hay)
     */
    public function add_and_notify(int $crId, string $body, string $authorType, string $authorName, string $visibility = 'internal'): int {
        $commentId = $this->commentModel->add($crId, $body, $authorType, $authorName, $visibility);
        if($commentId<=0) return 0;

        $recipients = [];
        $subject = '';
        $html = '';

        $cr = $this->get_cr($crId);
        $base = $this->base_url();
        $adminLink  = $base.'/admin/scopeguard/view?id='.$crId;

        if($authorType === 'staff' && $visibility === 'external'){
            // Notificar a clientes (aprobadores rol Client)
            $recipients = $this->client_emails($crId);
            $publicLinks = $this->role_links($crId, 'Client'); // si existen tokens por rol
            $subject = 'Nuevo comentario en solicitud: '.$cr['title'];
            $html  = '<p>Hola,</p>';
            $html .= '<p>Hay un nuevo comentario del equipo en la solicitud <b>'.htmlspecialchars($cr['title']).'</b>.</p>';
            $html .= '<blockquote style="border-left:3px solid #ddd;padding-left:8px">'.nl2br(htmlspecialchars($body)).'</blockquote>';
            if(!empty($publicLinks)){
                $html .= '<p>Puedes revisar y aprobar aquí:</p><ul>';
                foreach($publicLinks as $l){ $html.='<li><a href="'.$l.'" target="_blank">'.$l.'</a></li>'; }
                $html .= '</ul>';
            }
        } elseif ($authorType === 'client'){
            // Notificar a staff
            $recipients = $this->staff_emails($crId);
            if(empty($recipients) && !empty($this->cfg['notify']['staff_emails'])){
                $recipients = $this->cfg['notify']['staff_emails']; // respaldo desde config
            }
            $subject = 'Cliente comentó en solicitud: '.$cr['title'];
            $html  = '<p>Nuevo comentario del cliente en <b>'.htmlspecialchars($cr['title']).'</b>.</p>';
            $html .= '<blockquote style="border-left:3px solid #ddd;padding-left:8px">'.nl2br(htmlspecialchars($body)).'</blockquote>';
            $html .= '<p>Ver en admin: <a href="'.$adminLink.'" target="_blank">'.$adminLink.'</a></p>';
        }

        $recipients = array_values(array_unique(array_filter(array_map('trim',$recipients))));
        if(!empty($recipients)){
            $sent = $this->mailer->send($recipients, $subject, $html);
            if($sent){
                $this->commentModel->set_notified_to($commentId, $recipients);
            }
        }

        return $commentId;
    }

    private function get_cr(int $crId): array {
        $st = $this->pdo->prepare("SELECT id,title FROM scopeguard_change_requests WHERE id=?");
        $st->execute([$crId]); return (array)$st->fetch(PDO::FETCH_ASSOC);
    }

    private function base_url(): string {
        $cfg = $this->cfg;
        if(!empty($cfg['base_url'])) return rtrim($cfg['base_url'],'/');
        $sch = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https':'http';
        $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
        return $sch.'://'.$host;
    }

    /** Emails de aprobadores con rol "Client" */
    private function client_emails(int $crId): array {
        $rows = $this->approvalModel->list_by_cr($crId);
        $emails = [];
        foreach($rows as $r){
            if(strtolower($r['role'])==='client' && !empty($r['approver_email'])){
                $emails[] = $r['approver_email'];
            }
        }
        // respaldo: config notify.client_emails si quieres
        if(empty($emails) && !empty($this->cfg['notify']['client_emails'])){
            $emails = (array)$this->cfg['notify']['client_emails'];
        }
        return $emails;
    }

    /** Emails del staff: PM del flujo + lista fija en config */
    private function staff_emails(int $crId): array {
        $rows = $this->approvalModel->list_by_cr($crId);
        $emails = [];
        foreach($rows as $r){
            if(in_array(strtolower($r['role']),['pm','legal','staff'], true) && !empty($r['approver_email'])){
                $emails[] = $r['approver_email'];
            }
        }
        if(!empty($this->cfg['notify']['staff_emails'])){
            $emails = array_merge($emails, (array)$this->cfg['notify']['staff_emails']);
        }
        return array_values(array_unique($emails));
    }

    /** Enlaces públicos para un rol (si ya se emitieron tokens) */
    private function role_links(int $crId, string $role): array {
        $base = $this->base_url();
        $rows = $this->approvalModel->list_by_cr($crId);
        $links = [];
        foreach($rows as $r){
            if(strtolower($r['role'])===strtolower($role) && !empty($r['token'])){
                $links[] = $base.'/scope-approve/'.rawurlencode($r['token']);
            }
        }
        return $links;
    }
}
