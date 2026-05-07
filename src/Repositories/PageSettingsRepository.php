<?php

declare(strict_types=1);

namespace App\Repositories;

final class PageSettingsRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * Same row order strategy as legacy array index: ORDER BY psid ASC.
     *
     * @return list<array{psid:int,psname:string,is_active:int}>
     */
    public function allOrderedByPsid(): array
    {
        $sql = 'SELECT psid, psname, is_active FROM page_settings_data ORDER BY psid ASC';
        $st = $this->pdo->query($sql);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int|string, 'Y'|'N'> $psidToFlag
     */
    public function updateActiveFlags(array $psidToFlag): void
    {
        $upd = $this->pdo->prepare('UPDATE page_settings_data SET is_active = :active WHERE psid = :psid');
        foreach ($psidToFlag as $psid => $flag) {
            $active = ($flag === 'Y' || $flag === 'y') ? 1 : 0;
            $upd->execute([
                ':active' => $active,
                ':psid' => (int) $psid,
            ]);
        }
    }
}
