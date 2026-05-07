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

    /** FTP upload (step “Upload to server”) — fill all to enable. */
    // 'ftp_host' => 'ftp.yourhost.com',
    // 'ftp_port' => 21,
    // 'ftp_user' => 'username',
    // 'ftp_password' => 'secret', // may be empty for rare anonymous setups
    /** Remote directory to cd into before uploading (e.g. /public_html or /domains/site/public_html) */
    // 'ftp_remote_dir' => '/public_html',
    // 'ftp_passive' => true,
];
