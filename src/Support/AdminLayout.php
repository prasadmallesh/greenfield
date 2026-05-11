<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Staff layout helpers: legacy dashboard sidebar counts (product.php / party.php badges).
 */
final class AdminLayout
{
    /**
     * @return array{productCount:int, partyCount:int}
     */
    public static function sidebarCounts(\PDO $pdo): array
    {
        $ut = (string) ($_SESSION['login_user_type'] ?? '');
        $uid = (int) ($_SESSION['login_user_id'] ?? 0);

        $pc = (int) $pdo->query('SELECT COUNT(*) FROM product_mast')->fetchColumn();
        if ($ut === 'A') {
            $st = $pdo->query("SELECT COUNT(*) FROM party_mast WHERE is_active = '1'");
            $par = $st ? (int) $st->fetchColumn() : 0;
        } else {
            $st = $pdo->prepare("SELECT COUNT(*) FROM party_mast WHERE is_active = '1' AND uid = :u");
            $st->execute([':u' => $uid]);
            $par = (int) $st->fetchColumn();
        }

        return ['productCount' => $pc, 'partyCount' => $par];
    }

    /**
     * @param array<string, mixed> $viewData
     * @return array<string, mixed>
     */
    public static function with(\PDO $pdo, array $viewData): array
    {
        return array_merge($viewData, [
            'adminSidebarCounts' => self::sidebarCounts($pdo),
        ]);
    }
}
