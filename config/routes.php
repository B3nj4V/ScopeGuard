<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Rutas públicas del módulo ScopeGuard (controlador del módulo con prefijo)
$route['scope-approve/(:any)'] = 'scopeguard/scopeguard_client/landing/$1';
$route['scope-action/(:any)']  = 'scopeguard/scopeguard_client/action/$1';
