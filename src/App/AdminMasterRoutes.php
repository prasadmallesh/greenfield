<?php

declare(strict_types=1);

namespace App\App;

use App\Repositories\CategoryMasterRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\FuelMasterRepository;
use App\Repositories\PageSettingsRepository;
use App\Repositories\PartyMasterRepository;
use App\Repositories\PartyRepository;
use App\Repositories\ProductMasterRepository;
use App\Repositories\TaxMasterRepository;
use App\Repositories\UnitMasterRepository;
use App\Services\MenuPermissionService;
use App\Support\HttpUtil;
use App\Support\PayTerms;
use App\View\PhpRenderer;
use Slim\Routing\RouteCollectorProxy;

final class AdminMasterRoutes
{
    public static function register(
        RouteCollectorProxy $group,
        PhpRenderer $view,
        string $base,
        \PDO $pdo,
        PageSettingsRepository $pageRepo,
        PartyRepository $partyRepo,
    ): void {
        $catM = new CategoryMasterRepository($pdo);
        $prodM = new ProductMasterRepository($pdo);
        $partyM = new PartyMasterRepository($pdo);
        $taxM = new TaxMasterRepository($pdo);
        $unitM = new UnitMasterRepository($pdo);
        $fuelM = new FuelMasterRepository($pdo);
        $catShop = new CategoryRepository($pdo);

        $menu = static fn (): MenuPermissionService => new MenuPermissionService($pageRepo);

        $flashGet = static function (): array {
            $f = $_SESSION['admin_flash'] ?? [];
            unset($_SESSION['admin_flash']);

            return is_array($f) ? $f : [];
        };
        $flashSet = static function (string $type, string $msg): void {
            $_SESSION['admin_flash'] = ['type' => $type, 'msg' => $msg];
        };

        $staffUid = static fn (): int => (int) ($_SESSION['login_user_id'] ?? 0);
        $staffType = static fn (): string => (string) ($_SESSION['login_user_type'] ?? '');

        $canParty = static function (int $partyId) use ($partyM, $staffUid, $staffType): bool {
            if ($staffType() === 'A') {
                return true;
            }
            $row = $partyM->findById($partyId);

            return $row !== null && (int) ($row['uid'] ?? 0) === $staffUid();
        };

        $group->map(['GET', 'POST'], '/masters/categories', function (\Psr\Http\Message\ServerRequestInterface $req) use (
            $view, $base, $catM, $menu, $flashGet, $flashSet
        ) {
            $flash = $flashGet();
            $msg = (string) ($flash['msg'] ?? '');
            $alert = (string) ($flash['type'] ?? '');
            if ($req->getMethod() === 'POST') {
                $p = (array) $req->getParsedBody();
                $action = (string) ($p['form_action'] ?? '');
                if ($action === 'save') {
                    $name = strtoupper(trim((string) ($p['catname'] ?? '')));
                    $editId = (int) ($p['hd_edit_id'] ?? 0);
                    if ($name === '') {
                        $flashSet('err', 'Category name is required.');
                    } elseif ($editId <= 0) {
                        if ($catM->existsNameOtherThan($name, null)) {
                            $flashSet('err', 'Duplicate category name.');
                        } else {
                            $catM->insert($name);
                            $flashSet('ok', 'Record Added Successfully.');
                        }
                    } else {
                        if ($catM->existsNameOtherThan($name, $editId)) {
                            $flashSet('err', 'Duplicate category name.');
                        } else {
                            $catM->update($editId, $name);
                            $flashSet('ok', 'Record Updated Successfully.');
                        }
                    }
                } elseif ($action === 'delete') {
                    $id = (int) ($p['delete_id'] ?? 0);
                    if ($id > 0) {
                        $catM->delete($id);
                        $flashSet('ok', 'Record Deleted Successfully.');
                    }
                } else {
                    $flashSet('err', 'Invalid form submission.');
                }
                return HttpUtil::redirect($base . '/admin/masters/categories');
            }
            $q = $req->getQueryParams();
            $editRow = null;
            if (($q['act'] ?? '') === 'E') {
                $editRow = $catM->findById((int) ($q['id'] ?? 0));
            }

            return HttpUtil::html($view->render('admin/masters_categories', [
                'base' => $base,
                'menu' => $menu(),
                'rows' => $catM->listAll(),
                'editRow' => $editRow,
                'msg' => $msg,
                'alert' => $alert,
            ]));
        });

        $group->map(['GET', 'POST'], '/masters/tax', function (\Psr\Http\Message\ServerRequestInterface $req) use (
            $view, $base, $taxM, $menu, $flashGet, $flashSet
        ) {
            $flash = $flashGet();
            $msg = (string) ($flash['msg'] ?? '');
            $alert = (string) ($flash['type'] ?? '');
            if ($req->getMethod() === 'POST') {
                $p = (array) $req->getParsedBody();
                $action = (string) ($p['form_action'] ?? '');
                if ($action === 'save') {
                    $nm = strtoupper(trim((string) ($p['taxnm'] ?? '')));
                    $val = trim((string) ($p['taxval'] ?? ''));
                    $ord = trim((string) ($p['order_no'] ?? ''));
                    $editId = (int) ($p['hd_edit_id'] ?? 0);
                    if ($nm === '' || $val === '') {
                        $flashSet('err', 'Tax name and value are required.');
                    } elseif ($editId <= 0) {
                        $taxM->insert($nm, $val, $ord);
                        $flashSet('ok', 'Record Added Successfully.');
                    } else {
                        $taxM->update($editId, $nm, $val, $ord);
                        $flashSet('ok', 'Record Updated Successfully.');
                    }
                } elseif ($action === 'delete') {
                    $id = (int) ($p['delete_id'] ?? 0);
                    if ($id > 0) {
                        $taxM->delete($id);
                        $flashSet('ok', 'Record Deleted Successfully.');
                    }
                } else {
                    $flashSet('err', 'Invalid form submission.');
                }
                return HttpUtil::redirect($base . '/admin/masters/tax');
            }
            $q = $req->getQueryParams();
            $editRow = null;
            if (($q['act'] ?? '') === 'E') {
                $editRow = $taxM->findById((int) ($q['id'] ?? 0));
            }

            return HttpUtil::html($view->render('admin/masters_tax', [
                'base' => $base,
                'menu' => $menu(),
                'rows' => $taxM->listAll(),
                'editRow' => $editRow,
                'msg' => $msg,
                'alert' => $alert,
            ]));
        });

        $group->map(['GET', 'POST'], '/masters/units', function (\Psr\Http\Message\ServerRequestInterface $req) use (
            $view, $base, $unitM, $menu, $flashGet, $flashSet
        ) {
            $flash = $flashGet();
            $msg = (string) ($flash['msg'] ?? '');
            $alert = (string) ($flash['type'] ?? '');
            if ($req->getMethod() === 'POST') {
                $p = (array) $req->getParsedBody();
                $action = (string) ($p['form_action'] ?? '');
                if ($action === 'save') {
                    $nm = strtoupper(trim((string) ($p['unitnm'] ?? '')));
                    $editId = (int) ($p['hd_edit_id'] ?? 0);
                    if ($nm === '') {
                        $flashSet('err', 'Unit name is required.');
                    } elseif ($editId <= 0) {
                        $unitM->insert($nm);
                        $flashSet('ok', 'Record Added Successfully.');
                    } else {
                        $unitM->update($editId, $nm);
                        $flashSet('ok', 'Record Updated Successfully.');
                    }
                } elseif ($action === 'delete') {
                    $id = (int) ($p['delete_id'] ?? 0);
                    if ($id > 0) {
                        $unitM->delete($id);
                        $flashSet('ok', 'Record Deleted Successfully.');
                    }
                } else {
                    $flashSet('err', 'Invalid form submission.');
                }
                return HttpUtil::redirect($base . '/admin/masters/units');
            }
            $q = $req->getQueryParams();
            $editRow = null;
            if (($q['act'] ?? '') === 'E') {
                $editRow = $unitM->findById((int) ($q['id'] ?? 0));
            }

            return HttpUtil::html($view->render('admin/masters_units', [
                'base' => $base,
                'menu' => $menu(),
                'rows' => $unitM->listAll(),
                'editRow' => $editRow,
                'msg' => $msg,
                'alert' => $alert,
            ]));
        });

        $group->map(['GET', 'POST'], '/masters/fuel', function (\Psr\Http\Message\ServerRequestInterface $req) use (
            $view, $base, $fuelM, $menu, $flashGet, $flashSet
        ) {
            $flash = $flashGet();
            $msg = (string) ($flash['msg'] ?? '');
            $alert = (string) ($flash['type'] ?? '');
            if ($req->getMethod() === 'POST') {
                $p = (array) $req->getParsedBody();
                $action = (string) ($p['form_action'] ?? '');
                if ($action === 'save') {
                    $nm = trim((string) ($p['fuel_name'] ?? ''));
                    $cost = (float) ($p['fuel_cost'] ?? 0);
                    $act = isset($p['is_active']) ? 1 : 0;
                    $editId = (int) ($p['hd_edit_id'] ?? 0);
                    if ($nm === '') {
                        $flashSet('err', 'Fuel name is required.');
                    } elseif ($editId <= 0) {
                        if ($fuelM->existsName($nm, null)) {
                            $flashSet('err', 'Record Already Exist.');
                        } else {
                            $fuelM->insert($nm, $cost, $act);
                            $flashSet('ok', 'Record Added Successfully.');
                        }
                    } else {
                        $fuelM->update($editId, $nm, $cost, $act);
                        $flashSet('ok', 'Record Updated Successfully.');
                    }
                } elseif ($action === 'delete') {
                    $id = (int) ($p['delete_id'] ?? 0);
                    if ($id > 0) {
                        $fuelM->delete($id);
                        $flashSet('ok', 'Data Deleted Successfully.');
                    }
                } else {
                    $flashSet('err', 'Invalid form submission.');
                }
                return HttpUtil::redirect($base . '/admin/masters/fuel');
            }
            $q = $req->getQueryParams();
            $editRow = null;
            if (($q['act'] ?? '') === 'E') {
                $editRow = $fuelM->findById((int) ($q['id'] ?? 0));
            }

            return HttpUtil::html($view->render('admin/masters_fuel', [
                'base' => $base,
                'menu' => $menu(),
                'rows' => $fuelM->listAll(),
                'editRow' => $editRow,
                'msg' => $msg,
                'alert' => $alert,
            ]));
        });

        $group->map(['GET', 'POST'], '/masters/products', function (\Psr\Http\Message\ServerRequestInterface $req) use (
            $view, $base, $prodM, $catShop, $unitM, $menu, $flashGet, $flashSet, $staffUid, $staffType
        ) {
            $flash = $flashGet();
            $msg = (string) ($flash['msg'] ?? '');
            $alert = (string) ($flash['type'] ?? '');
            if ($req->getMethod() === 'POST') {
                $p = (array) $req->getParsedBody();
                $action = (string) ($p['form_action'] ?? '');
                if ($action === 'save') {
                    $catid = (int) ($p['category'] ?? 0);
                    $pname = strtoupper(trim((string) ($p['pname'] ?? '')));
                    $desc = strtoupper(trim((string) ($p['description'] ?? '')));
                    $tax = isset($p['tax']) ? 1 : 0;
                    $punit = trim((string) ($p['punit'] ?? ''));
                    $minc = (float) ($p['mincost'] ?? 0);
                    $pur = (float) ($p['purchase_cost'] ?? 0);
                    $isAct = isset($p['is_active']) ? 1 : 0;
                    $editId = (int) ($p['hd_edit_id'] ?? 0);
                    if ($catid <= 0 || $pname === '' || $punit === '') {
                        $flashSet('err', 'Category, product name, and unit are required.');
                    } elseif ($editId <= 0) {
                        $prodM->insertProduct($catid, $pname, $desc, $tax, $punit, '', $minc, $isAct, $pur);
                        $flashSet('ok', 'Record Added Successfully.');
                    } else {
                        $prodM->updateProduct($editId, $catid, $pname, $desc, $tax, $punit, null, $minc, $isAct, $pur);
                        $flashSet('ok', 'Record Updated Successfully.');
                    }
                } elseif ($action === 'delete') {
                    $id = (int) ($p['delete_id'] ?? 0);
                    if ($id > 0) {
                        $prodM->delete($id);
                        $flashSet('ok', 'Record Deleted Successfully.');
                    }
                } else {
                    $flashSet('err', 'Invalid form submission.');
                }
                return HttpUtil::redirect($base . '/admin/masters/products');
            }
            $q = $req->getQueryParams();
            $editRow = null;
            if (($q['act'] ?? '') === 'E') {
                $editRow = $prodM->findByIdWithPurchase((int) ($q['id'] ?? 0));
            }

            return HttpUtil::html($view->render('admin/masters_products', [
                'base' => $base,
                'menu' => $menu(),
                'rows' => $prodM->listAll(),
                'categories' => $catShop->listCategoriesMaster(500),
                'units' => $unitM->listAll(200),
                'editRow' => $editRow,
                'msg' => $msg,
                'alert' => $alert,
            ]));
        });

        $group->map(['GET', 'POST'], '/masters/parties', function (\Psr\Http\Message\ServerRequestInterface $req) use (
            $view, $base, $partyM, $partyRepo, $menu, $flashGet, $flashSet, $staffUid, $staffType, $canParty
        ) {
            $flash = $flashGet();
            $msg = (string) ($flash['msg'] ?? '');
            $alert = (string) ($flash['type'] ?? '');
            if ($req->getMethod() === 'POST') {
                $p = (array) $req->getParsedBody();
                $action = (string) ($p['form_action'] ?? '');
                if ($action === 'save') {
                    $editId = (int) ($p['hd_edit_id'] ?? 0);
                    $d = self::normalizePartyPost($p, $staffUid(), $staffType());
                    if ($d['partynm'] === '' || $d['address'] === '' || $d['city'] === '' || $d['email'] === '') {
                        $flashSet('err', 'Party name, address, city, and email are required.');
                    } elseif ($editId > 0 && !$canParty($editId)) {
                        $flashSet('err', 'Not allowed to edit this party.');
                    } else {
                        if ($editId <= 0) {
                            if ($partyM->existsPartyName($d['partynm'], null)) {
                                $flashSet('err', 'Record Already Exist.');
                            } else {
                                $newId = $partyM->insert($d);
                                $partyM->applyPartyAccountNumber($newId);
                                $flashSet('ok', 'Record Added Successfully.');
                            }
                        } else {
                            if ($partyM->existsPartyName($d['partynm'], $editId)) {
                                $flashSet('err', 'Record Already Exist.');
                            } elseif ($d['is_active'] === 0 && $partyM->partyHasInvoiceBalance($editId)) {
                                $flashSet('err', 'Party balance amount is exist. so you can not deactive this account.');
                            } else {
                                $partyM->update($editId, $d);
                                $flashSet('ok', 'Record Updated Successfully.');
                            }
                        }
                    }
                } elseif ($action === 'delete') {
                    $id = (int) ($p['delete_id'] ?? 0);
                    if ($id > 0 && $canParty($id) && $staffType() === 'A') {
                        $partyM->deleteWithSales($id);
                        $flashSet('ok', 'Party Deleted Successfully.');
                    } elseif ($id > 0) {
                        $flashSet('err', 'Only admin users can delete a party.');
                    }
                } else {
                    $flashSet('err', 'Invalid form submission.');
                }
                return HttpUtil::redirect($base . '/admin/masters/parties');
            }
            $q = $req->getQueryParams();
            $editRow = null;
            if (($q['act'] ?? '') === 'E') {
                $eid = (int) ($q['id'] ?? 0);
                if ($eid > 0 && $canParty($eid)) {
                    $editRow = $partyM->findById($eid);
                }
            }

            return HttpUtil::html($view->render('admin/masters_parties', [
                'base' => $base,
                'menu' => $menu(),
                'rows' => $partyM->listForMaster($staffUid(), $staffType(), 500),
                'editRow' => $editRow,
                'msg' => $msg,
                'alert' => $alert,
                'payTerms' => PayTerms::labelList(),
                'staffUsers' => $partyRepo->listUsersForSelect(),
            ]));
        });
    }

    /**
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    public static function normalizePartyPost(array $p, int $staffUid, string $staffType): array
    {
        $uid = (int) ($p['puser'] ?? 0);
        if ($staffType === 'U') {
            $uid = $staffUid;
        }

        return [
            'partynm' => strtoupper(trim((string) ($p['partynm'] ?? ''))),
            'address' => strtoupper(trim((string) ($p['paddress'] ?? ''))),
            'city' => strtoupper(trim((string) ($p['city'] ?? ''))),
            'state' => strtoupper(trim((string) ($p['state'] ?? ''))),
            'contactno' => trim((string) ($p['contactno'] ?? '')),
            'tinno' => trim((string) ($p['tinno'] ?? '')),
            'email' => trim((string) ($p['email'] ?? '')),
            'email2' => trim((string) ($p['email2'] ?? '')),
            'sper' => strtoupper(trim((string) ($p['sper'] ?? ''))),
            'pterms' => strtoupper(trim((string) ($p['pterms'] ?? 'COD'))),
            'deliveryins' => strtoupper(trim((string) ($p['deliveryins'] ?? ''))),
            'abnno' => strtoupper(trim((string) ($p['abnno'] ?? ''))),
            'is_active' => isset($p['pstatus']) ? 1 : 0,
            'hide_invoice' => isset($p['hide_invoice']) ? 1 : 0,
            'cpersonm' => strtoupper(trim((string) ($p['cpersonm'] ?? ''))),
            'outstanding_limit_amt' => trim((string) ($p['outstanding_limit_amt'] ?? '0')),
            'ignore_pro_minprice' => isset($p['ignore_pro_minprice']) ? 1 : 0,
            'uid' => $uid,
        ];
    }
}
