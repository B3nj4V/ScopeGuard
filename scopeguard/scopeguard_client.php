<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Scopeguard_client extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('scopeguard/scopeguard_token');
        $this->load->model('scopeguard/change_request_model');
        $this->load->model('scopeguard/scopeguard_signature_model');
        $this->load->model('scopeguard/scopeguard_audit_model');
    }

    // Página de aterrizaje (ver detalle + botones)
    public function landing($token)
    {
        $payload = $this->scopeguard_token->verify($token);
        if (!$payload) show_error(_l('scopeguard_token_invalid'), 403);

        $cr = $this->change_request_model->find((int)$payload['cr_id']);
        if (!$cr) show_404();

        $data['cr'] = $cr;
        $data['token'] = $token;
        $this->load->view('scopeguard/public/landing', $data);
    }

    // Acción 1 clic: aprobar o rechazar: /scope-action/{token}?do=approve|reject
    public function action($token)
    {
        $payload = $this->scopeguard_token->verify($token);
        if (!$payload) show_error(_l('scopeguard_token_invalid'), 403);

        $do = $this->input->get('do', true);
        if (!in_array($do, ['approve','reject'], true)) show_404();

        $cr = $this->change_request_model->find((int)$payload['cr_id']);
        if (!$cr) show_404();

        if ($do === 'approve') {
            $this->change_request_model->approve($cr['id']);
        } else {
            $this->change_request_model->reject($cr['id']);
        }

        $this->scopeguard_signature_model->add([
            'change_request_id' => $cr['id'],
            'signer_type' => 'client',
            'action'      => $do,
            'ip'          => $this->input->ip_address(),
            'user_agent'  => substr($this->input->user_agent(),0,250),
        ]);

        $this->scopeguard_audit_model->log($cr['id'], $do, [], null);
        // (Opcional) Si aprueba: aquí creas ítem de factura o hito pagable.

        // Redirige a una pantallita simple de resultado
        redirect('scope-approve/'.$token.'?status='.$do);
    }
}
