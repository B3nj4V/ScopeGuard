<?php
defined('BASEPATH') or exit('No direct script access allowed');

class scopeguard extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('scopeguard/change_request_model');
        $this->load->model('scopeguard/scopeguard_audit_model');
        $this->load->library('scopeguard/scopeguard_token');
        $this->load->library('scopeguard/scopeguard_pdf');
    }

    public function index()
    {
        if (!has_permission('scopeguard','','view')) access_denied('scopeguard');
        $data['title'] = _l('scopeguard');
        $data['rows']  = $this->change_request_model->all_for_admin();
        $this->load->view('scopeguard/admin/list', $data);
    }

    public function create()
    {
        if ($this->input->post()) {
            if (!has_permission('scopeguard','','create')) access_denied('scopeguard');
            $id = $this->change_request_model->create([
                'project_id' => (int)$this->input->post('project_id'),
                'task_id'    => (int)$this->input->post('task_id'),
                'client_id'  => (int)$this->input->post('client_id'),
                'title'      => $this->input->post('title', true),
                'description'=> $this->input->post('description', false),
                'currency'   => $this->input->post('currency', true),
                'cost_delta' => (float)$this->input->post('cost_delta'),
                'time_delta_hours' => (int)$this->input->post('time_delta_hours'),
                'status'     => 'draft',
            ]);
            $this->scopeguard_audit_model->log($id, 'created', [], get_staff_user_id());
            set_alert('success', _l('added_successfully'));
            redirect(admin_url('scopeguard'));
        }
        $data['title'] = _l('scopeguard_new');
        $this->load->view('scopeguard/admin/form', $data);
    }

    public function send($id)
    {
        if (!has_permission('scopeguard','','edit')) access_denied('scopeguard');
        $cr = $this->change_request_model->find((int)$id);
        if (!$cr) show_404();

        $token = $this->scopeguard_token->issue(['cr_id'=>$cr['id']]);
        $this->change_request_model->set_public_token($cr['id'], $token, date('Y-m-d H:i:s', time()+60*get_option('scopeguard_token_ttl_minutes')));
        $this->scopeguard_audit_model->log($cr['id'], 'sent', ['token'=>$token], get_staff_user_id());

        // (Opcional) Enviar email al contacto del cliente con el link:
        // $link = site_url('scope-approve/'.$token);

        set_alert('success', _l('scopeguard_sent_ok'));
        redirect(admin_url('scopeguard'));
    }

    public function pdf($id)
    {
        if (!has_permission('scopeguard','','view')) access_denied('scopeguard');
        $cr = $this->change_request_model->find((int)$id);
        if (!$cr) show_404();
        $html = $this->scopeguard_pdf->render_change_request($cr);
        app_pdf('scope_request_'.$cr['id'].'.pdf')->create($html);
    }
}
