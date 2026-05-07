<?php

declare(strict_types=1);

namespace App\Repositories;

final class AdminUserRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * @return array{uid:int,user_name:string,utype:string,payin:int,payout:int}|null
     */
    public function findByUsernameAndPasswordMd5(string $username, string $plainPassword): ?array
    {
        $sql = 'SELECT uid, user_name, utype, payin, payout FROM admin_user
                WHERE user_name = :u AND password = MD5(:p) LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute([':u' => $username, ':p' => $plainPassword]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
