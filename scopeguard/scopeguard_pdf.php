<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Scopeguard_pdf
{
    public function render_change_request(array $cr): string
    {
        // Usa librería PDF de Perfex (app_pdf) si quieres; aquí HTML simple
        $html = '<h2>Solicitud de Cambio</h2>';
        $html .= '<p><strong>Título:</strong> ' . html_escape($cr['title']) . '</p>';
        $html .= '<p><strong>Descripción:</strong><br>' . nl2br(html_escape($cr['description'])) . '</p>';
        $html .= '<p><strong>Costo extra:</strong> ' . $cr['currency'] . ' ' . number_format((float)$cr['cost_delta'], 2) . '</p>';
        $html .= '<p><strong>Tiempo extra:</strong> ' . (int)$cr['time_delta_hours'] . ' horas</p>';
        return $html;
    }
}

