<?php

declare(strict_types=1);

namespace App\App;

use App\Http\Captcha;
use App\Middleware\CustomerAuthMiddleware;
use App\Middleware\StaffAuthMiddleware;
use App\Repositories\AdminUserRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ClientWhatsappRepository;
use App\Repositories\CustomerCartRepository;
use App\Repositories\FuelRepository;
use App\Repositories\PageSettingsRepository;
use App\Repositories\PartyRepository;
use App\Repositories\PreorderRepository;
use App\Repositories\ProductMasterRepository;
use App\Repositories\SaleAccessRepository;
use App\Services\CustomerCheckoutService;
use App\Services\CustomerDeliveryDateService;
use App\Services\BillSettlementService;
use App\Services\InvoicePdfService;
use App\Services\MenuPermissionService;
use App\Services\PreorderAdminService;
use App\Services\SaleBillNumberService;
use App\Support\HttpUtil;
use App\View\PhpRenderer;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

final class RouteRegistrar
{
    public static function register(App $app, \PDO $pdo): void
    {
        $root = dirname(__DIR__, 2);
        $base = rtrim((string) ($_ENV['APP_BASE_PATH'] ?? ''), '/');
        $view = new PhpRenderer($root . '/templates');

        $pageRepo = new PageSettingsRepository($pdo);
        $partyRepo = new PartyRepository($pdo);
        $preorderRepo = new PreorderRepository($pdo);
        $adminRepo = new AdminUserRepository($pdo);
        $waRepo = new ClientWhatsappRepository($pdo);
        $catRepo = new CategoryRepository($pdo);
        $cartRepo = new CustomerCartRepository($pdo);
        $fuelRepo = new FuelRepository($pdo);
        $saleAccess = new SaleAccessRepository($pdo);
        $billSvc = new SaleBillNumberService($pdo);
        $deliverySvc = new CustomerDeliveryDateService();
        $checkoutSvc = new CustomerCheckoutService($pdo, $billSvc, $deliverySvc);
        $pdfSvc = new InvoicePdfService($pdo);
        $productMaster = new ProductMasterRepository($pdo);
        $preorderAdmin = new PreorderAdminService($pdo, $catRepo, $deliverySvc, $fuelRepo, $preorderRepo);
        $billSettlement = new BillSettlementService($pdo);

        $adminPub = [$base . '/admin/login', $base . '/admin/captcha'];
        $shopPub = [$base . '/shop', $base . '/shop/captcha'];

        $app->get($base . '/', function () use ($view, $base) {
            return HttpUtil::html($view->render('hub', ['base' => $base]));
        });

        /* -------- Staff: public -------- */
        $app->group($base . '/admin', function (RouteCollectorProxy $group) use ($view, $base, $adminRepo): void {
            $group->get('/captcha', function () {
                return Captcha::pngResponse('staff_captcha_code');
            });
            $group->map(['GET', 'POST'], '/login', function (\Psr\Http\Message\ServerRequestInterface $req) use ($view, $base, $adminRepo) {
                if ($req->getMethod() === 'POST') {
                    $p = (array) $req->getParsedBody();
                    $user = trim((string) ($p['username'] ?? ''));
                    $pwd = (string) ($p['pwd'] ?? '');
                    $code = trim((string) ($p['captchacode'] ?? ''));
                    $msg = '';
                    if ($user === '' || $pwd === '' || $code === '') {
                        $msg = 'Please enter username, password, and security code.';
                    } elseif (!Captcha::verify('staff_captcha_code', $code)) {
                        $msg = 'Invalid security code.';
                    } else {
                        $row = $adminRepo->findByUsernameAndPasswordMd5($user, $pwd);
                        if ($row === null) {
                            $msg = 'Invalid username or password.';
                        } else {
                            $_SESSION['login_user_id'] = (int) $row['uid'];
                            $_SESSION['login_user'] = (string) $row['user_name'];
                            $_SESSION['login_user_type'] = (string) $row['utype'];
                            $_SESSION['login_payin'] = (int) $row['payin'];
                            $_SESSION['login_payout'] = (int) $row['payout'];

                            return HttpUtil::redirect($base . '/admin/preorders');
                        }
                    }

                    return HttpUtil::html($view->render('admin/login', ['msg' => $msg, 'base' => $base]));
                }

                return HttpUtil::html($view->render('admin/login', ['msg' => '', 'base' => $base]));
            });
        });

        /* -------- Staff: protected -------- */
        $app->group($base . '/admin', function (RouteCollectorProxy $group) use (
            $view,
            $base,
            $pdo,
            $pageRepo,
            $partyRepo,
            $preorderRepo,
            $catRepo,
            $pdfSvc,
            $saleAccess,
            $fuelRepo,
            $deliverySvc,
            $preorderAdmin,
            $productMaster,
            $billSettlement,
        ): void {
            $group->get('/logout', function () use ($base) {
                unset($_SESSION['login_user_id'], $_SESSION['login_user'], $_SESSION['login_user_type'], $_SESSION['login_payin'], $_SESSION['login_payout']);

                return HttpUtil::redirect($base . '/admin/login');
            });

            $group->get('', function () use ($view, $base, $pageRepo) {
                $menu = new MenuPermissionService($pageRepo);

                return HttpUtil::html($view->render('admin/dashboard', ['base' => $base, 'menu' => $menu]));
            });

            $group->get('/calendar', function () use ($view, $base, $pageRepo) {
                $menu = new MenuPermissionService($pageRepo);

                return HttpUtil::html($view->render('admin/calendar_stub', ['base' => $base, 'menu' => $menu]));
            });

            $group->get('/preorders', function (\Psr\Http\Message\ServerRequestInterface $req) use ($view, $base, $pageRepo, $partyRepo, $preorderRepo) {
                $menu = new MenuPermissionService($pageRepo);
                $q = $req->getQueryParams();
                $fdt = isset($q['fdt']) ? (string) $q['fdt'] : '';
                $tdt = isset($q['tdt']) ? (string) $q['tdt'] : '';
                $pid = isset($q['pid']) ? (int) $q['pid'] : 0;
                $puid = isset($q['puid']) ? (int) $q['puid'] : 0;
                $ut = (string) ($_SESSION['login_user_type'] ?? '');
                $uid = (int) ($_SESSION['login_user_id'] ?? 0);
                $csv = $ut === 'A' ? null : $partyRepo->partyIdsCsvForUser($uid);
                $rows = $preorderRepo->listPreorders(
                    $ut,
                    $uid,
                    $csv,
                    $menu->canIncludeStandardPreorderUnion(),
                    $fdt !== '' ? $fdt : null,
                    $tdt !== '' ? $tdt : null,
                    $pid > 0 ? $pid : null,
                    $puid > 0 ? $puid : null,
                    250,
                );

                return HttpUtil::html($view->render('admin/preorders', [
                    'base' => $base,
                    'menu' => $menu,
                    'rows' => $rows,
                    'parties' => $ut === 'A' ? $partyRepo->listPartiesForSelect() : [],
                    'users' => $ut === 'A' ? $partyRepo->listUsersForSelect() : [],
                    'fdt' => $fdt,
                    'tdt' => $tdt,
                    'pid' => $pid,
                    'puid' => $puid,
                ]));
            });

            $toDmY = static function ($v): string {
                if ($v === null || $v === '') {
                    return date('d/m/Y');
                }
                $s = trim((string) $v);
                if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $s) === 1) {
                    return $s;
                }
                $t = strtotime($s);

                return $t ? date('d/m/Y', $t) : date('d/m/Y');
            };

            $parsePreorderLines = static function (array $p): array {
                $j = trim((string) ($p['lines_json'] ?? ''));
                $d = json_decode($j, true);
                if (!is_array($d)) {
                    return [];
                }
                $out = [];
                foreach ($d as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $out[] = [
                        'pid' => (int) ($row['pid'] ?? 0),
                        'qty' => (float) ($row['qty'] ?? 0),
                        'punit' => trim((string) ($row['punit'] ?? '')),
                        'rate' => (float) ($row['rate'] ?? 0),
                    ];
                }

                return $out;
            };

            $group->get('/preorders/{sbid}/edit', function (\Psr\Http\Message\ServerRequestInterface $req, \Psr\Http\Message\ResponseInterface $res, array $args) use (
                $view,
                $base,
                $pageRepo,
                $partyRepo,
                $preorderAdmin,
                $productMaster,
                $fuelRepo,
                $deliverySvc,
                $saleAccess,
                $toDmY,
            ) {
                $sbid = trim((string) ($args['sbid'] ?? ''));
                $ut = (string) ($_SESSION['login_user_type'] ?? '');
                $uid = (int) ($_SESSION['login_user_id'] ?? 0);
                $csv = $ut === 'A' ? null : $partyRepo->partyIdsCsvForUser($uid);
                if (!$saleAccess->staffCanAccessSale($ut, $uid, $csv, $sbid)) {
                    return HttpUtil::html('Forbidden', 403);
                }
                $data = $preorderAdmin->loadEditablePreorder($sbid);
                if ($data === null) {
                    return HttpUtil::redirect($base . '/admin/preorders');
                }
                $menu = new MenuPermissionService($pageRepo);
                $flash = $_SESSION['preorder_edit_flash'] ?? ['type' => '', 'msg' => ''];
                unset($_SESSION['preorder_edit_flash']);
                $h = $data['header'];
                $invdt = $toDmY($h['sdate'] ?? '');
                $dvSel = $toDmY($h['deliverydt'] ?? '');
                $cur = date('Y-m-d', strtotime('+0 days'));
                $end = date('Y-m-d', strtotime('+20 days'));
                $dvopts = $deliverySvc->displayDateOptions($cur, $end);
                if ($dvopts !== [] && !array_key_exists($dvSel, $dvopts)) {
                    $first = array_key_first($dvopts);
                    $dvSel = $first !== null ? $first : $invdt;
                }
                if ($dvopts === []) {
                    $dvopts = [$invdt => 'Today'];
                    $dvSel = $invdt;
                }

                return HttpUtil::html($view->render('admin/preorder_edit', [
                    'base' => $base,
                    'menu' => $menu,
                    'sbid' => $sbid,
                    'header' => $h,
                    'lines' => $data['lines'],
                    'products' => $productMaster->listAll(2000),
                    'fuel' => $fuelRepo->activeFuelRows(),
                    'dvopts' => $dvopts,
                    'payOpts' => PreorderAdminService::payModeOptions(),
                    'invdt' => $invdt,
                    'dvSelected' => $dvSel,
                    'flashMsg' => (string) ($flash['msg'] ?? ''),
                    'flashType' => (string) ($flash['type'] ?? ''),
                ]));
            });

            $group->post('/preorders/{sbid}/modify', function (\Psr\Http\Message\ServerRequestInterface $req, \Psr\Http\Message\ResponseInterface $res, array $args) use (
                $base,
                $partyRepo,
                $preorderAdmin,
                $saleAccess,
                $parsePreorderLines,
            ) {
                $sbid = trim((string) ($args['sbid'] ?? ''));
                $ut = (string) ($_SESSION['login_user_type'] ?? '');
                $uid = (int) ($_SESSION['login_user_id'] ?? 0);
                $csv = $ut === 'A' ? null : $partyRepo->partyIdsCsvForUser($uid);
                if (!$saleAccess->staffCanAccessSale($ut, $uid, $csv, $sbid)) {
                    return HttpUtil::html('Forbidden', 403);
                }
                $p = (array) $req->getParsedBody();
                $lines = $parsePreorderLines($p);
                $err = $preorderAdmin->savePreorder(
                    $sbid,
                    trim((string) ($p['invdt'] ?? '')),
                    trim((string) ($p['paymode'] ?? 'C')),
                    (string) ($p['spnote'] ?? ''),
                    trim((string) ($p['dvdt'] ?? '')),
                    $lines,
                    false,
                );
                if ($err !== '') {
                    $_SESSION['preorder_edit_flash'] = ['type' => 'err', 'msg' => $err];
                } else {
                    $_SESSION['preorder_edit_flash'] = ['type' => 'ok', 'msg' => 'Pre-order updated.'];
                }

                return HttpUtil::redirect($base . '/admin/preorders/' . rawurlencode($sbid) . '/edit');
            });

            $group->post('/preorders/{sbid}/finalize', function (\Psr\Http\Message\ServerRequestInterface $req, \Psr\Http\Message\ResponseInterface $res, array $args) use (
                $base,
                $partyRepo,
                $preorderAdmin,
                $saleAccess,
                $parsePreorderLines,
            ) {
                $sbid = trim((string) ($args['sbid'] ?? ''));
                $ut = (string) ($_SESSION['login_user_type'] ?? '');
                $uid = (int) ($_SESSION['login_user_id'] ?? 0);
                $csv = $ut === 'A' ? null : $partyRepo->partyIdsCsvForUser($uid);
                if (!$saleAccess->staffCanAccessSale($ut, $uid, $csv, $sbid)) {
                    return HttpUtil::html('Forbidden', 403);
                }
                $p = (array) $req->getParsedBody();
                $lines = $parsePreorderLines($p);
                $err = $preorderAdmin->savePreorder(
                    $sbid,
                    trim((string) ($p['invdt'] ?? '')),
                    trim((string) ($p['paymode'] ?? 'C')),
                    (string) ($p['spnote'] ?? ''),
                    trim((string) ($p['dvdt'] ?? '')),
                    $lines,
                    true,
                );
                if ($err !== '') {
                    $_SESSION['preorder_edit_flash'] = ['type' => 'err', 'msg' => $err];

                    return HttpUtil::redirect($base . '/admin/preorders/' . rawurlencode($sbid) . '/edit');
                }

                return HttpUtil::redirect($base . '/admin/invoices/' . rawurlencode($sbid) . '/created');
            });

            $group->post('/preorders/{sbid}/delete', function (\Psr\Http\Message\ServerRequestInterface $req, \Psr\Http\Message\ResponseInterface $res, array $args) use (
                $base,
                $partyRepo,
                $preorderAdmin,
                $saleAccess,
            ) {
                $sbid = trim((string) ($args['sbid'] ?? ''));
                $ut = (string) ($_SESSION['login_user_type'] ?? '');
                $uid = (int) ($_SESSION['login_user_id'] ?? 0);
                $csv = $ut === 'A' ? null : $partyRepo->partyIdsCsvForUser($uid);
                if (!$saleAccess->staffCanAccessSale($ut, $uid, $csv, $sbid)) {
                    return HttpUtil::html('Forbidden', 403);
                }
                $err = $preorderAdmin->deletePreorder($sbid);
                if ($err !== '') {
                    $_SESSION['preorder_edit_flash'] = ['type' => 'err', 'msg' => $err];

                    return HttpUtil::redirect($base . '/admin/preorders/' . rawurlencode($sbid) . '/edit');
                }

                return HttpUtil::redirect($base . '/admin/preorders');
            });

            $group->map(['GET', 'POST'], '/page-manager', function (\Psr\Http\Message\ServerRequestInterface $req) use ($view, $base, $pageRepo) {
                if ((string) ($_SESSION['login_user_type'] ?? '') !== 'A') {
                    return HttpUtil::redirect($base . '/admin');
                }
                $msg = '';
                if ($req->getMethod() === 'POST') {
                    $p = (array) $req->getParsedBody();
                    $ps = $p['ps'] ?? [];
                    if (is_array($ps)) {
                        $map = [];
                        foreach ($ps as $psid => $flag) {
                            $map[(int) $psid] = ($flag === 'Y' || $flag === 'y') ? 'Y' : 'N';
                        }
                        $pageRepo->updateActiveFlags($map);
                        $msg = 'Record has been updated.';
                    }
                }
                $menu = new MenuPermissionService($pageRepo);

                return HttpUtil::html($view->render('admin/page_manager', [
                    'base' => $base,
                    'menu' => $menu,
                    'rows' => $pageRepo->allOrderedByPsid(),
                    'msg' => $msg,
                ]));
            });

            AdminMasterRoutes::register($group, $view, $base, $pdo, $pageRepo, $partyRepo);

            $group->get('/invoices/{sbid}/created', function (\Psr\Http\Message\ServerRequestInterface $req, \Psr\Http\Message\ResponseInterface $res, array $args) use ($view, $base, $pageRepo, $partyRepo, $saleAccess) {
                $sbid = (string) ($args['sbid'] ?? '');
                $ut = (string) ($_SESSION['login_user_type'] ?? '');
                $uid = (int) ($_SESSION['login_user_id'] ?? 0);
                $csv = $ut === 'A' ? null : $partyRepo->partyIdsCsvForUser($uid);
                if (!$saleAccess->staffCanAccessSale($ut, $uid, $csv, $sbid)) {
                    return HttpUtil::html('Forbidden', 403);
                }
                $menu = new MenuPermissionService($pageRepo);
                $pdfUrl = $base . '/admin/invoices/' . rawurlencode($sbid) . '/pdf';

                return HttpUtil::html($view->render('admin/invoice_created', [
                    'base' => $base,
                    'menu' => $menu,
                    'sbid' => $sbid,
                    'pdfUrl' => $pdfUrl,
                ]));
            });

            $group->get('/invoices/{sbid}/pdf', function (\Psr\Http\Message\ServerRequestInterface $req, \Psr\Http\Message\ResponseInterface $res, array $args) use ($base, $pdfSvc, $partyRepo, $saleAccess) {
                $sbid = (string) ($args['sbid'] ?? '');
                $ut = (string) ($_SESSION['login_user_type'] ?? '');
                $uid = (int) ($_SESSION['login_user_id'] ?? 0);
                $csv = $ut === 'A' ? null : $partyRepo->partyIdsCsvForUser($uid);
                if (!$saleAccess->staffCanAccessSale($ut, $uid, $csv, $sbid)) {
                    return HttpUtil::html('Forbidden', 403);
                }
                try {
                    $bin = $pdfSvc->renderPreorderPdfBinary($sbid);
                    $res = $res->withHeader('Content-Type', 'application/pdf')
                        ->withHeader('Content-Disposition', 'inline; filename="' . rawurlencode($sbid) . '.pdf"');
                    $res->getBody()->write($bin);

                    return $res;
                } catch (\Throwable $e) {
                    return HttpUtil::html('PDF error: ' . htmlspecialchars($e->getMessage(), \ENT_QUOTES, 'UTF-8'), 500);
                }
            });

            $group->get('/bill-settlement', function (\Psr\Http\Message\ServerRequestInterface $req) use (
                $view,
                $base,
                $pageRepo,
                $partyRepo,
                $billSettlement,
                $saleAccess,
            ) {
                $menu = new MenuPermissionService($pageRepo);
                $ut = (string) ($_SESSION['login_user_type'] ?? '');
                $uid = (int) ($_SESSION['login_user_id'] ?? 0);
                $invAny = $menu->isActiveAtIndex(6) || $menu->isActiveAtIndex(7) || $menu->isActiveAtIndex(15);
                if ($ut !== 'A' && !$invAny) {
                    return HttpUtil::html('Forbidden', 403);
                }
                $q = $req->getQueryParams();
                $pmode = isset($q['pmode']) ? (string) $q['pmode'] : 'P';
                if ($pmode !== 'C') {
                    $pmode = 'P';
                }
                $fdt = isset($q['fdt']) ? (string) $q['fdt'] : '';
                $tdt = isset($q['tdt']) ? (string) $q['tdt'] : '';
                $pid = isset($q['pid']) ? (int) $q['pid'] : 0;
                $cid = isset($q['cid']) ? (int) $q['cid'] : 0;
                $svRaw = isset($q['sv']) ? (string) $q['sv'] : '';
                $sv = '';
                if ($svRaw !== '') {
                    $sv = 'GREEN' . str_replace('GREEN', '', strtoupper(trim($svRaw)));
                }
                $csv = $ut === 'A' ? null : $partyRepo->partyIdsCsvForUser($uid);
                $tarr = [];
                if ($fdt !== '' || $tdt !== '' || $pid > 0 || $cid > 0 || $sv !== '') {
                    $tarr = $billSettlement->getOutstandingInvoiceRows($fdt, $tdt, $pid, $cid, $sv, $csv);
                    $tarr = array_values(array_filter($tarr, static function (array $r) use ($saleAccess, $ut, $uid, $csv): bool {
                        return $saleAccess->staffCanAccessParty($ut, $uid, $csv, (int) ($r['partyid'] ?? 0));
                    }));
                    if ($sv !== '' && $tarr !== [] && isset($tarr[0]['partyid'])) {
                        $pid = (int) $tarr[0]['partyid'];
                    }
                }
                $crparr = [];
                if ($pid > 0) {
                    if (!$saleAccess->staffCanAccessParty($ut, $uid, $csv, $pid)) {
                        return HttpUtil::html('Forbidden', 403);
                    }
                    $crparr = $billSettlement->listPartyCreditInvoices($pid);
                }
                $partyRows = $partyRepo->listPartiesScoped($uid, $ut);
                $custRows = $ut === 'A' ? $partyRepo->listCustomersForSelect() : [];
                $contact = $pid > 0 ? $partyRepo->getPartyCustomerByPartyId($pid) : [];
                $addr = '';
                $cperson = '';
                $cmob = '';
                $cemail = '';
                if ($contact !== []) {
                    $c0 = $contact[0];
                    $addr = trim((string) ($c0['address'] ?? '') . (($c0['city'] ?? '') !== '' ? ', ' . (string) $c0['city'] : '') . (($c0['state'] ?? '') !== '' ? ', ' . (string) $c0['state'] : ''));
                    $cperson = (string) ($c0['cpersonm'] ?? '');
                    $cmob = (string) ($c0['contactno'] ?? '');
                    $cemail = strtolower((string) ($c0['email'] ?? ''));
                }
                $pbrow = $billSettlement->getOpeningBalanceRow($pmode, $pid, $cid);
                $pbEntry = null;
                if ($pbrow !== null) {
                    if ($pmode === 'P' && $pid > 0 && $saleAccess->staffCanAccessParty($ut, $uid, $csv, $pid)) {
                        $pbEntry = $pbrow;
                    }
                    if ($pmode === 'C' && $cid > 0 && $ut === 'A') {
                        $pbEntry = $pbrow;
                    }
                }
                $flash = $_SESSION['bill_settlement_flash'] ?? ['type' => '', 'msg' => ''];
                unset($_SESSION['bill_settlement_flash']);

                return HttpUtil::html($view->render('admin/bill_settlement', [
                    'base' => $base,
                    'menu' => $menu,
                    'ptyarr' => BillSettlementService::settlementPayModeOptions(),
                    'pmode' => $pmode,
                    'fdt' => $fdt,
                    'tdt' => $tdt,
                    'pid' => $pid,
                    'cid' => $cid,
                    'sv' => $svRaw,
                    'tarr' => $tarr,
                    'crparr' => $crparr,
                    'partyRows' => $partyRows,
                    'custRows' => $custRows,
                    'paddress' => $addr,
                    'cperson' => $cperson,
                    'cmobno' => $cmob,
                    'cpemail' => $cemail,
                    'pbEntry' => $pbEntry,
                    'flashMsg' => (string) ($flash['msg'] ?? ''),
                    'flashType' => (string) ($flash['type'] ?? ''),
                ]));
            });

            $group->get('/bill-settlement/api/credit', function (\Psr\Http\Message\ServerRequestInterface $req) use ($base, $partyRepo, $billSettlement, $saleAccess) {
                $ut = (string) ($_SESSION['login_user_type'] ?? '');
                $uid = (int) ($_SESSION['login_user_id'] ?? 0);
                $csv = $ut === 'A' ? null : $partyRepo->partyIdsCsvForUser($uid);
                $q = $req->getQueryParams();
                $sbid = strtoupper(trim((string) ($q['sbid'] ?? '')));
                if ($sbid === '') {
                    return HttpUtil::json(['price' => '0.00'], 400);
                }
                $pid = $saleAccess->partyIdForSale($sbid);
                if ($pid === null || !$saleAccess->staffCanAccessParty($ut, $uid, $csv, $pid)) {
                    return HttpUtil::json(['price' => '0.00'], 403);
                }
                $price = number_format($billSettlement->getRemainingCreditOnInvoice($sbid), 2, '.', '');

                return HttpUtil::json(['price' => $price]);
            });

            $group->post('/bill-settlement/save', function (\Psr\Http\Message\ServerRequestInterface $req) use ($base, $partyRepo, $billSettlement, $saleAccess) {
                $ut = (string) ($_SESSION['login_user_type'] ?? '');
                $uid = (int) ($_SESSION['login_user_id'] ?? 0);
                $csv = $ut === 'A' ? null : $partyRepo->partyIdsCsvForUser($uid);
                $p = (array) $req->getParsedBody();
                $chk = $p['chk'] ?? [];
                if (!is_array($chk)) {
                    $chk = $chk !== null && $chk !== '' ? [$chk] : [];
                }
                $modearr = is_array($p['pbmode'] ?? null) ? $p['pbmode'] : [];
                foreach ($chk as $keyRaw) {
                    $key = (string) $keyRaw;
                    $mode = (string) ($modearr[$key] ?? '');
                    if ($mode === 'IV') {
                        $spid = $saleAccess->partyIdForSale($key);
                        if ($spid === null || !$saleAccess->staffCanAccessParty($ut, $uid, $csv, $spid)) {
                            return HttpUtil::html('Forbidden', 403);
                        }
                    }
                }
                $ctype = '';
                if (!empty($p['ctype']) && is_array($p['ctype'])) {
                    $ctype = (string) $p['ctype'][0];
                }
                $partyid = (int) (!empty($p['pname']) ? $p['pname'] : ($p['hd_partynm'] ?? 0));
                if ($ctype === 'P' && !$saleAccess->staffCanAccessParty($ut, $uid, $csv, $partyid)) {
                    return HttpUtil::html('Forbidden', 403);
                }
                if ($ctype === 'C' && $ut !== 'A') {
                    return HttpUtil::html('Forbidden', 403);
                }

                $n = $billSettlement->saveFromPost($p);
                $_SESSION['bill_settlement_flash'] = [
                    'type' => str_contains((string) $n['msg'], 'Successfully') ? 'ok' : 'err',
                    'msg' => (string) $n['msg'],
                ];
                $pidR = (int) ($n['partyid'] ?? 0);
                $cidR = (int) ($n['custid'] ?? 0);
                $pm = (string) ($n['ctype'] ?? 'P') === 'C' ? 'C' : 'P';
                $q = $pm === 'P' && $pidR > 0 ? '?pmode=P&pid=' . $pidR : ($pm === 'C' && $cidR > 0 ? '?pmode=C&cid=' . $cidR : '?pmode=P');

                return HttpUtil::redirect($base . '/admin/bill-settlement' . $q);
            });

            $group->get('/reports', function () use ($view, $base, $pageRepo) {
                $menu = new MenuPermissionService($pageRepo);

                return HttpUtil::html($view->render('admin/reports_stub', ['base' => $base, 'menu' => $menu]));
            });
        })->add(new StaffAuthMiddleware($base . '/admin/login', $adminPub));

        /* -------- Customer: public -------- */
        $app->group($base . '/shop', function (RouteCollectorProxy $group) use ($view, $base, $waRepo, $partyRepo, $cartRepo): void {
            $group->get('/captcha', function () {
                return Captcha::pngResponse('customer_captcha_code');
            });
            $group->map(['GET', 'POST'], '', function (\Psr\Http\Message\ServerRequestInterface $req) use ($view, $base, $waRepo, $partyRepo, $cartRepo) {
                if ($req->getMethod() === 'POST') {
                    $p = (array) $req->getParsedBody();
                    $mob = trim((string) ($p['wmob'] ?? ''));
                    $code = trim((string) ($p['captchacode'] ?? ''));
                    $msg = '';
                    if ($mob === '' || $code === '') {
                        $msg = 'Please enter mobile no. and security code.';
                    } elseif (!Captcha::verify('customer_captcha_code', $code)) {
                        $msg = 'Invalid Security Code.';
                    } else {
                        $partyId = $waRepo->resolvePartyIdFromMobile($mob);
                        if ($partyId === null) {
                            $msg = 'Invalid Mobile No.';
                        } else {
                            $pt = $partyRepo->getPartyCustomerByPartyId($partyId);
                            if ($pt === []) {
                                $msg = 'Invalid Mobile No.';
                            } else {
                                $cartRepo->clearCart($partyId);
                                $_SESSION['customer_partyid'] = $partyId;
                                $_SESSION['customer_partynm'] = (string) ($pt[0]['partynm'] ?? '');
                                $_SESSION['customer_mobno'] = $mob;

                                return HttpUtil::redirect($base . '/shop/menu');
                            }
                        }
                    }

                    return HttpUtil::html($view->render('shop/login', ['msg' => $msg, 'base' => $base]));
                }

                return HttpUtil::html($view->render('shop/login', ['msg' => '', 'base' => $base]));
            });
        });

        /* -------- Customer: protected -------- */
        $app->group($base . '/shop', function (RouteCollectorProxy $group) use (
            $view,
            $base,
            $catRepo,
            $cartRepo,
            $fuelRepo,
            $partyRepo,
            $checkoutSvc,
            $pdfSvc,
            $saleAccess,
            $deliverySvc,
        ): void {
            $group->get('/logout', function () use ($base) {
                unset($_SESSION['customer_partyid'], $_SESSION['customer_partynm'], $_SESSION['customer_mobno']);

                return HttpUtil::redirect($base . '/shop');
            });

            $group->get('/menu', function () use ($view, $base, $catRepo) {
                $pid = (int) ($_SESSION['customer_partyid'] ?? 0);
                $cats = $catRepo->categoriesForParty($pid);

                return HttpUtil::html($view->render('shop/menu', ['base' => $base, 'cats' => $cats]));
            });

            $group->get('/category/{id}', function (\Psr\Http\Message\ServerRequestInterface $req, \Psr\Http\Message\ResponseInterface $res, array $args) use ($view, $base, $catRepo) {
                $catId = (int) ($args['id'] ?? 0);
                $pid = (int) ($_SESSION['customer_partyid'] ?? 0);
                $products = $catRepo->productsInCategoryForParty($catId, $pid);

                return HttpUtil::html($view->render('shop/category', [
                    'base' => $base,
                    'catId' => $catId,
                    'products' => $products,
                ]));
            });

            $group->post('/cart/add', function (\Psr\Http\Message\ServerRequestInterface $req) use ($base, $catRepo, $cartRepo) {
                $p = (array) $req->getParsedBody();
                $partyId = (int) ($_SESSION['customer_partyid'] ?? 0);
                $payterm = $cartRepo->partyPayterm($partyId);
                $pid = (int) ($p['pid'] ?? 0);
                $qty = (float) ($p['qty'] ?? 0);
                $punit = trim((string) ($p['punit'] ?? ''));
                $rate = (float) ($p['rate'] ?? 0);
                if ($pid <= 0 || $qty <= 0 || $punit === '' || $rate <= 0) {
                    return HttpUtil::redirect($base . '/shop/menu');
                }
                $amt = $qty * $rate;
                $taxInclude = $catRepo->productTaxInclude($pid);
                if ($taxInclude === 1) {
                    $taxAmt = round($amt / 11, 2);
                    $net = $amt - $taxAmt;
                } else {
                    $taxAmt = 0.0;
                    $net = $amt;
                }
                $cartRepo->insertLine($partyId, $pid, $qty, $punit, $rate, $amt, $taxAmt, $net, $payterm);

                return HttpUtil::redirect($base . '/shop/cart');
            });

            $group->get('/cart/delete', function (\Psr\Http\Message\ServerRequestInterface $req) use ($base, $cartRepo) {
                $q = $req->getQueryParams();
                $pid = (int) ($q['pid'] ?? 0);
                $partyId = (int) ($_SESSION['customer_partyid'] ?? 0);
                if ($pid > 0) {
                    $cartRepo->deleteLine($partyId, $pid);
                }

                return HttpUtil::redirect($base . '/shop/cart');
            });

            $group->map(['GET', 'POST'], '/cart', function (\Psr\Http\Message\ServerRequestInterface $req) use ($view, $base, $cartRepo, $fuelRepo, $partyRepo, $checkoutSvc, $deliverySvc) {
                $partyId = (int) ($_SESSION['customer_partyid'] ?? 0);
                $fuel = $fuelRepo->activeFuelRows();
                $cur = date('Y-m-d', strtotime('+0 days'));
                $end = date('Y-m-d', strtotime('+20 days'));
                $dvopts = $deliverySvc->displayDateOptions($cur, $end);
                $paycode = $partyRepo->payModeCodeForParty($partyId);

                if ($req->getMethod() === 'POST') {
                    $p = (array) $req->getParsedBody();
                    if (($p['btnsubmit'] ?? '') === 'Submit') {
                        $n = $checkoutSvc->finalizeCustomerPreorder($partyId, $p);
                        if (($n['sale_bill_no'] ?? '') !== '') {
                            $_SESSION['flash_sale_bill'] = $n['sale_bill_no'];

                            return HttpUtil::redirect($base . '/shop/thank-you');
                        }
                        $err = (string) ($n['msg'] ?? 'Could not submit order.');
                        $lines = $cartRepo->cartLines($partyId);

                        return HttpUtil::html($view->render('shop/cart', [
                            'base' => $base,
                            'lines' => $lines,
                            'fuel' => $fuel,
                            'dvopts' => $dvopts,
                            'paycode' => $paycode,
                            'error' => $err,
                        ]));
                    }
                }

                $lines = $cartRepo->cartLines($partyId);

                return HttpUtil::html($view->render('shop/cart', [
                    'base' => $base,
                    'lines' => $lines,
                    'fuel' => $fuel,
                    'dvopts' => $dvopts,
                    'paycode' => $paycode,
                    'error' => '',
                ]));
            });

            $group->get('/thank-you', function () use ($view, $base) {
                $sb = (string) ($_SESSION['flash_sale_bill'] ?? '');
                unset($_SESSION['flash_sale_bill']);

                return HttpUtil::html($view->render('shop/thank_you', ['base' => $base, 'sbid' => $sb]));
            });

            $group->get('/invoice/{sbid}/pdf', function (\Psr\Http\Message\ServerRequestInterface $req, \Psr\Http\Message\ResponseInterface $res, array $args) use ($pdfSvc, $saleAccess) {
                $sbid = (string) ($args['sbid'] ?? '');
                $partyId = (int) ($_SESSION['customer_partyid'] ?? 0);
                if (!$saleAccess->customerOwnsSale($partyId, $sbid)) {
                    return HttpUtil::html('Forbidden', 403);
                }
                try {
                    $bin = $pdfSvc->renderPreorderPdfBinary($sbid);
                    $res = $res->withHeader('Content-Type', 'application/pdf')
                        ->withHeader('Content-Disposition', 'inline; filename="' . rawurlencode($sbid) . '.pdf"');
                    $res->getBody()->write($bin);

                    return $res;
                } catch (\Throwable $e) {
                    return HttpUtil::html('PDF error: ' . htmlspecialchars($e->getMessage(), \ENT_QUOTES, 'UTF-8'), 500);
                }
            });
        })->add(new CustomerAuthMiddleware($base . '/shop', $shopPub));
    }
}
