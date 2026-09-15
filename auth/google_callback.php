<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$oauthContext = $_SESSION['google_oauth'] ?? null;
unset($_SESSION['google_oauth']);

if (!is_array($oauthContext)
    || !isset($oauthContext['state'], $oauthContext['nonce'], $oauthContext['code_verifier'], $oauthContext['expires_at'])
    || time() > (int) $oauthContext['expires_at']
    || !isset($_GET['state'])
    || !hash_equals((string) $oauthContext['state'], (string) $_GET['state'])) {
    drw_google_flash_and_redirect('danger', 'Sesi Login Google tidak valid atau sudah kedaluwarsa. Silakan coba lagi.');
}

if (isset($_GET['error'])) {
    drw_google_flash_and_redirect('warning', 'Login Google dibatalkan atau tidak diizinkan.');
}

if (!isset($_GET['code']) || !is_string($_GET['code']) || $_GET['code'] === '') {
    drw_google_flash_and_redirect('danger', 'Google tidak mengirimkan kode otorisasi yang valid.');
}

try {
    $token = drw_google_fetch_token($_GET['code'], $oauthContext['code_verifier']);
    if (empty($token['id_token']) || !is_string($token['id_token'])) {
        throw new RuntimeException('Google tidak mengirimkan identitas akun yang valid.');
    }

    $payload = drw_google_client()->verifyIdToken($token['id_token']);
    if (!is_array($payload)) {
        throw new RuntimeException('Identitas Google tidak dapat diverifikasi.');
    }

    $validIssuers = ['accounts.google.com', 'https://accounts.google.com'];
    $audience = $payload['aud'] ?? null;
    $audienceMatches = is_array($audience)
        ? in_array(GOOGLE_CLIENT_ID, $audience, true)
        : $audience === GOOGLE_CLIENT_ID;

    if (!in_array($payload['iss'] ?? '', $validIssuers, true)
        || !$audienceMatches
        || !isset($payload['nonce'])
        || !hash_equals((string) $oauthContext['nonce'], (string) $payload['nonce'])
        || empty($payload['sub'])
        || empty($payload['email'])
        || empty($payload['email_verified'])) {
        throw new RuntimeException('Identitas Google tidak dapat diverifikasi.');
    }

    $googleSub = (string) $payload['sub'];
    $googleEmail = filter_var((string) $payload['email'], FILTER_VALIDATE_EMAIL);
    $fullName = trim((string) ($payload['name'] ?? ''));
    if ($googleEmail === false) {
        throw new RuntimeException('Email akun Google tidak valid.');
    }
    if ($fullName === '') {
        $fullName = strtok($googleEmail, '@') ?: 'Pasien DRW';
    }
    $fullName = substr($fullName, 0, 100);

    $statement = $conn->prepare('SELECT id_user, username, nama_lengkap, auth_provider FROM user WHERE google_sub = ? LIMIT 1');
    $statement->bind_param('s', $googleSub);
    $statement->execute();
    $googleUser = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (($oauthContext['mode'] ?? 'login') === 'link') {
        if (!isset($_SESSION['user_id'])) {
            throw new RuntimeException('Sesi akun pasien tidak ditemukan.');
        }
        if ($googleUser && (int) $googleUser['id_user'] !== (int) $_SESSION['user_id']) {
            throw new RuntimeException('Akun Google tersebut sudah terhubung ke pasien lain.');
        }

        $userId = (int) $_SESSION['user_id'];
        $statement = $conn->prepare("UPDATE user SET google_sub = ?, google_email = ?, auth_provider = 'google' WHERE id_user = ?");
        $statement->bind_param('ssi', $googleSub, $googleEmail, $userId);
        $statement->execute();
        $statement->close();

        drw_flash('success', 'Akun Google berhasil dihubungkan.');
        header('Location: ' . drw_app_url('profil.php'));
        exit();
    }

    if ($googleUser) {
        drw_login_user($googleUser);
        header('Location: ' . drw_app_url(drw_consume_post_login_redirect()));
        exit();
    }

    $statement = $conn->prepare('SELECT id_user FROM user WHERE google_email = ? LIMIT 1');
    $statement->bind_param('s', $googleEmail);
    $statement->execute();
    $existingEmailUser = $statement->get_result()->fetch_assoc();
    $statement->close();

    if ($existingEmailUser) {
        drw_google_flash_and_redirect('warning', 'Email ini sudah memiliki akun. Login dengan akun lama terlebih dahulu, lalu hubungkan Google dari halaman profil.');
    }

    $username = drw_google_available_username($conn, $googleSub);
    $statement = $conn->prepare("INSERT INTO user (nama_lengkap, username, password, email, google_sub, google_email, auth_provider) VALUES (?, ?, NULL, ?, ?, ?, 'google')");
    $statement->bind_param('sssss', $fullName, $username, $googleEmail, $googleSub, $googleEmail);
    $statement->execute();
    $userId = $statement->insert_id;
    $statement->close();

    drw_login_user([
        'id_user' => $userId,
        'username' => $username,
        'nama_lengkap' => $fullName,
        'auth_provider' => 'google',
    ]);
    drw_flash('success', 'Selamat datang di Klinik DRW Estetika. Lengkapi nomor WhatsApp saat mengajukan booking.');
    header('Location: ' . drw_app_url(drw_consume_post_login_redirect()));
    exit();
} catch (Throwable $exception) {
    error_log('Google OAuth failed: ' . $exception->getMessage());
    drw_google_flash_and_redirect('danger', 'Login Google belum dapat diselesaikan. Silakan coba kembali.');
}
