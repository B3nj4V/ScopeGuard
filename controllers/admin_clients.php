<?php
declare(strict_types=1);
require_once __DIR__.'/../models/client_model.php';
require_once __DIR__.'/../models/client_contact_model.php';

function admin_clients_router(PDO $pdo): void {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/admin/clients';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $clientM = new client_model($pdo);
    $contactM= new client_contact_model($pdo);

    /* ===== LISTADO ===== */
    if ($uri==='/admin/clients') {
        $q = trim($_GET['q'] ?? '');
        $page = max(1,(int)($_GET['p']??1)); $per=25; $off=($page-1)*$per;
        $res = $clientM->search($q,$per,$off);
        $rows=$res['rows']; $total=$res['total']; $pages=(int)ceil($total/$per);

        layout_header('Clientes · ScopeGuard');
        ?>
        <div class="panel_s">
            <div class="panel-heading">Clientes</div>
            <div class="panel-body">
                <form method="get" action="/admin/clients" class="form" style="margin-bottom:10px">
                    <div class="form-row" style="display:grid;grid-template-columns:2fr 120px;gap:8px">
                        <input class="form-control" name="q" placeholder="Buscar por nombre o VAT..." value="<?= h($q) ?>">
                        <button class="btn btn-primary">Buscar</button>
                    </div>
                </form>

                <div class="toolbar" style="margin-bottom:10px">
                    <a class="btn btn-primary" href="/admin/clients/create">+ Nuevo cliente</a>
                </div>

                <table class="table">
                    <thead><tr><th>ID</th><th>Nombre</th><th>VAT</th><th>Web</th><th>Teléfono</th><th>Moneda</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach($rows as $r): ?>
                        <tr>
                            <td>#<?= (int)$r['id'] ?></td>
                            <td><?= h($r['name']) ?></td>
                            <td><?= h($r['vat']) ?></td>
                            <td class="small"><?= h($r['website']) ?></td>
                            <td class="small"><?= h($r['phone']) ?></td>
                            <td><?= h($r['currency']) ?></td>
                            <td><a class="btn btn-default" href="/admin/clients/view?id=<?= (int)$r['id'] ?>">Ver</a> <a class="btn btn-default" href="/admin/clients/edit?id=<?= (int)$r['id'] ?>">Editar</a></td>
                        </tr>
                    <?php endforeach; if(empty($rows)): ?>
                        <tr><td colspan="7" class="small">Sin resultados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php if($pages>1): ?>
                    <div class="toolbar" style="margin-top:10px">
                        <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?>
                            <?php if($i===$page): ?><span class="btn btn-primary"><?= (int)$i ?></span>
                            <?php else: ?><a class="btn btn-default" href="/admin/clients?q=<?= urlencode($q) ?>&p=<?= (int)$i ?>"><?= (int)$i ?></a><?php endif; ?>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        layout_footer(); return;
    }

    /* ===== CREAR ===== */
    if ($uri==='/admin/clients/create') {
        if ($method==='POST') {
            $address = [
                'line1'=>trim($_POST['address_line1']??''),
                'city'=>trim($_POST['address_city']??''),
                'state'=>trim($_POST['address_state']??''),
                'zip'=>trim($_POST['address_zip']??''),
                'country'=>trim($_POST['address_country']??''),
            ];
            $id = $clientM->create([
                'name'=>$_POST['name']??'',
                'vat'=>$_POST['vat']??'',
                'website'=>$_POST['website']??'',
                'phone'=>$_POST['phone']??'',
                'currency'=>$_POST['currency']??'USD',
                'default_tax'=>(float)($_POST['default_tax']??0),
                'address'=>$address,
            ]);
            redirect_to('/admin/clients/view?id='.$id);
        }
        layout_header('Nuevo Cliente · ScopeGuard');
        ?>
        <div class="panel_s"><div class="panel-heading">Nuevo Cliente</div><div class="panel-body">
                <form method="post">
                    <div class="form-row" style="display:grid;grid-template-columns:2fr 1fr;gap:8px">
                        <div><label class="label">Nombre *</label><input class="form-control" name="name" required></div>
                        <div><label class="label">VAT</label><input class="form-control" name="vat"></div>
                    </div>
                    <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px">
                        <div><label class="label">Website</label><input class="form-control" name="website" placeholder="https://..."></div>
                        <div><label class="label">Teléfono</label><input class="form-control" name="phone"></div>
                        <div><label class="label">Moneda</label><input class="form-control" name="currency" value="USD"></div>
                    </div>
                    <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:8px;margin-top:8px">
                        <div><label class="label">Impuesto por defecto (%)</label><input class="form-control" type="number" step="0.01" name="default_tax" value="0"></div>
                        <div style="grid-column: span 4"></div>
                        <div style="grid-column: span 5"><label class="label">Dirección</label></div>
                        <div style="grid-column: span 5"><input class="form-control" name="address_line1" placeholder="Línea 1"></div>
                        <div><input class="form-control" name="address_city" placeholder="Ciudad"></div>
                        <div><input class="form-control" name="address_state" placeholder="Estado/Prov."></div>
                        <div><input class="form-control" name="address_zip" placeholder="ZIP"></div>
                        <div><input class="form-control" name="address_country" placeholder="País"></div>
                    </div>
                    <div class="toolbar" style="margin-top:10px">
                        <button class="btn btn-primary" type="submit">Guardar</button>
                        <a class="btn btn-default" href="/admin/clients">Cancelar</a>
                    </div>
                </form>
            </div></div>
        <?php
        layout_footer(); return;
    }

    /* ===== EDITAR ===== */
    if ($uri==='/admin/clients/edit') {
        $id=(int)($_GET['id']??0);
        $c=$clientM->find($id); if(!$c){ http_response_code(404); exit('Cliente no encontrado'); }
        if ($method==='POST') {
            $address = [
                'line1'=>trim($_POST['address_line1']??''),
                'city'=>trim($_POST['address_city']??''),
                'state'=>trim($_POST['address_state']??''),
                'zip'=>trim($_POST['address_zip']??''),
                'country'=>trim($_POST['address_country']??''),
            ];
            $clientM->update($id,[
                'name'=>$_POST['name']??$c['name'],
                'vat'=>$_POST['vat']??$c['vat'],
                'website'=>$_POST['website']??$c['website'],
                'phone'=>$_POST['phone']??$c['phone'],
                'currency'=>$_POST['currency']??$c['currency'],
                'default_tax'=>(float)($_POST['default_tax']??$c['default_tax']),
                'address'=>$address,
            ]);
            redirect_to('/admin/clients/view?id='.$id);
        }
        $c = $clientM->find($id);
        layout_header('Editar Cliente · ScopeGuard');
        ?>
        <div class="panel_s"><div class="panel-heading">Editar Cliente</div><div class="panel-body">
                <form method="post">
                    <div class="form-row" style="display:grid;grid-template-columns:2fr 1fr;gap:8px">
                        <div><label class="label">Nombre *</label><input class="form-control" name="name" value="<?= h($c['name']) ?>" required></div>
                        <div><label class="label">VAT</label><input class="form-control" name="vat" value="<?= h($c['vat']) ?>"></div>
                    </div>
                    <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px">
                        <div><label class="label">Website</label><input class="form-control" name="website" value="<?= h($c['website']) ?>"></div>
                        <div><label class="label">Teléfono</label><input class="form-control" name="phone" value="<?= h($c['phone']) ?>"></div>
                        <div><label class="label">Moneda</label><input class="form-control" name="currency" value="<?= h($c['currency']) ?>"></div>
                    </div>
                    <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:8px;margin-top:8px">
                        <div><label class="label">Impuesto por defecto (%)</label><input class="form-control" type="number" step="0.01" name="default_tax" value="<?= h((string)$c['default_tax']) ?>"></div>
                        <div style="grid-column: span 5"><label class="label">Dirección</label></div>
                        <div style="grid-column: span 5"><input class="form-control" name="address_line1" value="<?= h($c['address']['line1']??'') ?>"></div>
                        <div><input class="form-control" name="address_city" value="<?= h($c['address']['city']??'') ?>"></div>
                        <div><input class="form-control" name="address_state" value="<?= h($c['address']['state']??'') ?>"></div>
                        <div><input class="form-control" name="address_zip" value="<?= h($c['address']['zip']??'') ?>"></div>
                        <div><input class="form-control" name="address_country" value="<?= h($c['address']['country']??'') ?>"></div>
                    </div>
                    <div class="toolbar" style="margin-top:10px">
                        <button class="btn btn-primary" type="submit">Guardar</button>
                        <a class="btn btn-default" href="/admin/clients/view?id=<?= (int)$id ?>">Volver</a>
                    </div>
                </form>
            </div></div>
        <?php
        layout_footer(); return;
    }

    /* ===== FICHA ===== */
    if ($uri==='/admin/clients/view') {
        $id=(int)($_GET['id']??0);
        $c=$clientM->find($id); if(!$c){ http_response_code(404); exit('Cliente no encontrado'); }

        // pestaña
        $tab = $_GET['tab'] ?? 'crs';

        // contactos
        if ($method==='POST' && ($_POST['_action']??'')==='add_contact') {
            $contactM->add($id, [
                'name'=>$_POST['name']??'',
                'email'=>$_POST['email']??'',
                'phone'=>$_POST['phone']??'',
                'is_primary'=>!empty($_POST['is_primary']),
                'portal_enabled'=>!empty($_POST['portal_enabled']),
                'password'=>$_POST['password']??null,
            ]);
            redirect_to('/admin/clients/view?id='.$id.'#contacts');
        }
        if ($method==='POST' && ($_POST['_action']??'')==='primary' && !empty($_POST['contact_id'])) {
            $contactM->set_primary((int)$_POST['contact_id']);
            redirect_to('/admin/clients/view?id='.$id.'#contacts');
        }
        if ($method==='POST' && ($_POST['_action']??'')==='del_contact' && !empty($_POST['contact_id'])) {
            $contactM->delete((int)$_POST['contact_id']);
            redirect_to('/admin/clients/view?id='.$id.'#contacts');
        }

        $contacts = $contactM->list_by_client($id);

        // CRs del cliente
        $crs = $pdo->prepare("SELECT id,title,status,currency,cost_delta,created_at FROM scopeguard_change_requests WHERE client_id=? ORDER BY id DESC");
        $crs->execute([$id]); $crs=$crs->fetchAll(PDO::FETCH_ASSOC);

        layout_header('Cliente · '.$c['name']);
        ?>
        <div class="panel_s">
            <div class="panel-heading">Cliente: <?= h($c['name']) ?></div>
            <div class="panel-body">
                <div class="grid" style="display:grid;grid-template-columns:2fr 1fr;gap:12px">
                    <div>
                        <div class="kicker">Datos</div>
                        <p class="small">VAT: <b><?= h($c['vat'] ?: '—') ?></b> · Moneda: <b><?= h($c['currency']) ?></b> · Imp. def.: <b><?= (float)$c['default_tax'] ?>%</b></p>
                        <p class="small">Web: <?= h($c['website'] ?: '—') ?> · Tel: <?= h($c['phone'] ?: '—') ?></p>
                        <p class="small">Dir: <?= h(($c['address']['line1'] ?? '—').' '.($c['address']['city']??'')) ?></p>
                        <div class="toolbar" style="margin-top:6px"><a class="btn btn-default" href="/admin/clients/edit?id=<?= (int)$c['id'] ?>">Editar</a> <a class="btn btn-default" href="/admin/clients">Volver</a></div>
                    </div>
                    <div>
                        <div class="kicker">Acciones rápidas</div>
                        <a class="btn btn-primary" href="/admin/scopeguard/create?client_id=<?= (int)$c['id'] ?>">+ Nueva Solicitud de Cambio</a>
                    </div>
                </div>

                <hr>
                <div class="toolbar">
                    <a class="btn <?= $tab==='crs'?'btn-primary':'btn-default' ?>" href="/admin/clients/view?id=<?= (int)$id ?>&tab=crs">Solicitudes de Cambio</a>
                    <a class="btn <?= $tab==='projects'?'btn-primary':'btn-default' ?>" href="/admin/clients/view?id=<?= (int)$id ?>&tab=projects">Proyectos</a>
                    <a class="btn <?= $tab==='invoices'?'btn-primary':'btn-default' ?>" href="/admin/clients/view?id=<?= (int)$id ?>&tab=invoices">Facturas</a>
                    <a class="btn <?= $tab==='tickets'?'btn-primary':'btn-default' ?>" href="/admin/clients/view?id=<?= (int)$id ?>&tab=tickets">Tickets</a>
                </div>

                <?php if($tab==='crs'): ?>
                    <table class="table" style="margin-top:10px">
                        <thead><tr><th>ID</th><th>Título</th><th>Estado</th><th>Monto</th><th>Creado</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach($crs as $r): ?>
                            <tr>
                                <td>#<?= (int)$r['id'] ?></td>
                                <td><?= h($r['title']) ?></td>
                                <td><?= status_badge($r['status']) ?></td>
                                <td><?= h($r['currency']) ?> <?= number_format((float)$r['cost_delta'],2) ?></td>
                                <td class="small"><?= date('Y-m-d', (int)$r['created_at']) ?></td>
                                <td><a class="btn btn-default" href="/admin/scopeguard/view?id=<?= (int)$r['id'] ?>">Ver</a></td>
                            </tr>
                        <?php endforeach; if(empty($crs)): ?>
                            <tr><td colspan="6" class="small">Sin solicitudes aún.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="card" style="border:1px solid #e5e5e5;margin-top:10px"><div class="card-pad small">Pestaña “<?= h($tab) ?>” aún sin datos. (Proyectos/Facturas/Tickets por implementar)</div></div>
                <?php endif; ?>

                <hr id="contacts">
                <h4>Contactos</h4>
                <table class="table">
                    <thead><tr><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Principal</th><th>Portal</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach($contacts as $co): ?>
                        <tr>
                            <td><?= h($co['name']) ?></td>
                            <td><?= h($co['email']) ?></td>
                            <td><?= h($co['phone']) ?></td>
                            <td><?= !empty($co['is_primary']) ? '✔' : '' ?></td>
                            <td><?= !empty($co['portal_enabled']) ? '✔' : '' ?></td>
                            <td class="toolbar">
                                <?php if(empty($co['is_primary'])): ?>
                                    <form method="post" style="display:inline"><input type="hidden" name="_action" value="primary"><input type="hidden" name="contact_id" value="<?= (int)$co['id'] ?>"><button class="btn btn-default" type="submit">Hacer principal</button></form>
                                <?php endif; ?>
                                <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar contacto?');">
                                    <input type="hidden" name="_action" value="del_contact">
                                    <input type="hidden" name="contact_id" value="<?= (int)$co['id'] ?>">
                                    <button class="btn btn-default" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; if(empty($contacts)): ?>
                        <tr><td colspan="6" class="small">Sin contactos.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <form method="post" style="margin-top:10px">
                    <input type="hidden" name="_action" value="add_contact">
                    <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr 1fr 120px 120px;gap:8px">
                        <input class="form-control" name="name" placeholder="Nombre" required>
                        <input class="form-control" name="email" placeholder="Email">
                        <input class="form-control" name="phone" placeholder="Teléfono">
                        <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="is_primary"> Principal</label>
                        <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="portal_enabled"> Portal</label>
                    </div>
                    <div class="form-row" style="display:grid;grid-template-columns:1fr 200px;gap:8px;margin-top:8px">
                        <input class="form-control" name="password" placeholder="Password (opcional para portal)">
                        <button class="btn btn-default" type="submit">Agregar contacto</button>
                    </div>
                </form>

            </div>
        </div>
        <?php
        layout_footer(); return;
    }

    // Si ninguna ruta coincide:
    http_response_code(404);
    layout_header('Clientes · 404');
    echo '<div class="card"><div class="card-pad">404</div></div>';
    layout_footer();
}

