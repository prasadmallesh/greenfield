<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PageSettingsRepository;

/**
 * Mirrors legacy component/header.php checks against $menuarr[N]->is_active
 * where N is the zero-based index of rows from GetPageSettingsData ordered by psid.
 */
final class MenuPermissionService
{
    /** @var list<array{psid:int,psname:string,is_active:int}> */
    private array $rows;

    public function __construct(PageSettingsRepository $repo)
    {
        $raw = $repo->allOrderedByPsid();
        $this->rows = [];
        foreach ($raw as $r) {
            $this->rows[] = [
                'psid' => (int) $r['psid'],
                'psname' => (string) $r['psname'],
                'is_active' => (int) $r['is_active'],
            ];
        }
    }

    public function isActiveAtIndex(int $index): bool
    {
        return isset($this->rows[$index]) && $this->rows[$index]['is_active'] === 1;
    }

    /**
     * @return list<array{psid:int,psname:string,is_active:int}>
     */
    public function allRows(): array
    {
        return $this->rows;
    }

    /** Standard pre-order rows (standard_data) union — legacy menuarr[13]. */
    public function canIncludeStandardPreorderUnion(): bool
    {
        return $this->isActiveAtIndex(13);
    }
}
