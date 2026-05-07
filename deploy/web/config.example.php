<?php

declare(strict_types=1);

/**
 * Copy this file to config.local.php and set a strong password.
 * config.local.php is gitignored — never commit it.
 *
 * Run the UI only on localhost, or set allow_lan for trusted private networks.
 */
return [
    'password' => 'change-me-to-something-long',
    /** When false, only 127.0.0.1 and ::1 may use this UI. */
    'allow_lan' => false,
    /**
     * Optional: override GitHub remote for “Connect & push” (HTTPS or git@github.com:…/….git).
     * If omitted, the hub uses https://github.com/prasadmallesh/greenfield.git
     */
    // 'github_remote_url' => 'https://github.com/prasadmallesh/greenfield.git',
];
