<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin_auth.php';

drw_require_admin();
header('Location: kelola_afiliasi.php?tab=affiliates&aff_status=pending', true, 302);
exit();
