<?php
declare(strict_types=1);

require_once 'config.php';

drw_flash('info', 'Pendaftaran pasien dilakukan melalui Login Google. Silakan lanjutkan dengan Google untuk membuat akun.');
header('Location: ' . drw_app_url('login.php'));
exit();
