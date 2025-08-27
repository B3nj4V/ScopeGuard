<?php
// ScopeGuard/router.php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$path = __DIR__ . $uri;
// servir archivos estáticos si existen
if ($uri !== '/' && file_exists($path) && is_file($path)) { return false; }
// todo lo demás pasa por index.php
require __DIR__ . '/index.php';
