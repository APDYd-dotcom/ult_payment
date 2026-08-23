<?php

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Bacon\MatrixFactory;
use Endroid\QrCode\QrCode;
use RobThree\Auth\Providers\Qr\IQRCodeProvider;
use RobThree\Auth\TwoFactorAuth;

require_once __DIR__ . '/vendor/autoload.php';

if (!class_exists('UltSvgQrCodeProvider')) {
    final class UltSvgQrCodeProvider implements IQRCodeProvider
    {
        public function getQRCodeImage(string $qrText, int $size): string
        {
            $qrCode = new QrCode(
                data: $qrText,
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: $size,
                margin: 8,
                foregroundColor: new Color(30, 58, 138),
                backgroundColor: new Color(255, 255, 255)
            );

            $matrix = (new MatrixFactory())->create($qrCode);
            $outerSize = $matrix->getOuterSize();
            $foreground = $qrCode->getForegroundColor();
            $background = $qrCode->getBackgroundColor();
            $path = '';

            for ($row = 0; $row < $matrix->getBlockCount(); $row++) {
                $left = $matrix->getMarginLeft();

                for ($column = 0; $column < $matrix->getBlockCount(); $column++) {
                    if ($matrix->getBlockValue($row, $column) !== 1) {
                        continue;
                    }

                    if ($column === 0 || $matrix->getBlockValue($row, $column - 1) === 0) {
                        $left = $matrix->getMarginLeft() + $matrix->getBlockSize() * $column;
                    }

                    if ($column === $matrix->getBlockCount() - 1 || $matrix->getBlockValue($row, $column + 1) === 0) {
                        $top = $matrix->getMarginLeft() + $matrix->getBlockSize() * $row;
                        $bottom = $matrix->getMarginLeft() + $matrix->getBlockSize() * ($row + 1);
                        $right = $matrix->getMarginLeft() + $matrix->getBlockSize() * ($column + 1);
                        $path .= 'M' . self::formatNumber($left) . ',' . self::formatNumber($top);
                        $path .= 'L' . self::formatNumber($right) . ',' . self::formatNumber($top);
                        $path .= 'L' . self::formatNumber($right) . ',' . self::formatNumber($bottom);
                        $path .= 'L' . self::formatNumber($left) . ',' . self::formatNumber($bottom) . 'Z';
                    }
                }
            }

            return sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="%1$dpx" height="%1$dpx" viewBox="0 0 %1$d %1$d"><rect x="0" y="0" width="%1$d" height="%1$d" fill="%2$s" fill-opacity="%3$s"/><path fill="%4$s" fill-opacity="%5$s" d="%6$s"/></svg>',
                $outerSize,
                $background->getHex(),
                self::formatNumber($background->getOpacity()),
                $foreground->getHex(),
                self::formatNumber($foreground->getOpacity()),
                $path
            );
        }

        public function getMimeType(): string
        {
            return 'image/svg+xml';
        }

        private static function formatNumber(float $number): string
        {
            $string = number_format($number, 2, '.', '');
            $string = rtrim($string, '0');

            return rtrim($string, '.');
        }
    }
}

function ultTwoFactorAuth(): TwoFactorAuth
{
    return new TwoFactorAuth(new UltSvgQrCodeProvider(), 'ULT Payment System');
}

function ultTwoFactorNormalizeCode(string $code): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
}

function ultTwoFactorGenerateBackupCodes(int $count = 10): array
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $codes = [];

    while (count($codes) < $count) {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $codes[$code] = $code;
    }

    return array_values($codes);
}

function ultTwoFactorHashBackupCodes(array $codes): string
{
    $hashes = array_map(static function (string $code): string {
        return password_hash(ultTwoFactorNormalizeCode($code), PASSWORD_DEFAULT);
    }, $codes);

    return json_encode($hashes, JSON_THROW_ON_ERROR);
}

function ultTwoFactorFetchUser(PDO $bdd, int $userId): ?array
{
    $stmt = $bdd->prepare("
        SELECT userId, fullname, email, password, role,
               two_factor_enabled, two_factor_secret,
               two_factor_backup_codes, two_factor_confirmed_at
        FROM user
        WHERE userId = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function ultTwoFactorVerifyTotp(?string $secret, string $code): bool
{
    $code = preg_replace('/\s+/', '', $code) ?? '';
    if (!$secret || !preg_match('/^\d{6}$/', $code)) {
        return false;
    }

    return ultTwoFactorAuth()->verifyCode($secret, $code, 1);
}

function ultTwoFactorConsumeBackupCode(PDO $bdd, array $user, string $code): bool
{
    $code = ultTwoFactorNormalizeCode($code);
    if ($code === '') {
        return false;
    }

    $hashes = json_decode((string) ($user['two_factor_backup_codes'] ?? '[]'), true);
    if (!is_array($hashes)) {
        return false;
    }

    foreach ($hashes as $index => $hash) {
        if (is_string($hash) && password_verify($code, $hash)) {
            unset($hashes[$index]);
            $stmt = $bdd->prepare('UPDATE user SET two_factor_backup_codes = ? WHERE userId = ?');
            $stmt->execute([json_encode(array_values($hashes), JSON_THROW_ON_ERROR), (int) $user['userId']]);
            return true;
        }
    }

    return false;
}

function ultTwoFactorCompleteLogin(PDO $bdd, array $user): void
{
    session_regenerate_id(true);

    unset($_SESSION['2fa_pending_user'], $_SESSION['2fa_pending_started']);
    $_SESSION['email'] = $user['email'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['userId'] = $user['userId'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['last_activity'] = time();

    logLogin($bdd, $user['userId'], $user['email']);

    if ($user['role'] === 'admin') {
        header('Location: /payment/admin/dashboard.php');
    } else {
        header('Location: /payment/student/profile.php');
    }
    exit();
}

function ultTwoFactorHandleProfilePost(PDO $bdd, string &$error, string &$success): void
{
    $userId = (int) ($_SESSION['userId'] ?? 0);
    if ($userId <= 0) {
        return;
    }

    if (isset($_POST['start_2fa_setup'])) {
        $secret = ultTwoFactorAuth()->createSecret();
        $_SESSION['2fa_setup'] = [
            'secret' => $secret,
            'backup_codes' => ultTwoFactorGenerateBackupCodes(),
            'created_at' => time(),
        ];
        $success = 'Scannez le QR code puis saisissez le code à 6 chiffres pour activer la 2FA.';
        return;
    }

    if (isset($_POST['cancel_2fa_setup'])) {
        unset($_SESSION['2fa_setup']);
        $success = 'Configuration 2FA annulée.';
        return;
    }

    if (isset($_POST['confirm_2fa_setup'])) {
        $setup = $_SESSION['2fa_setup'] ?? null;
        $code = trim($_POST['totp_code'] ?? '');

        if (!is_array($setup) || empty($setup['secret']) || empty($setup['backup_codes'])) {
            $error = 'Veuillez recommencer la configuration 2FA.';
            return;
        }

        if (!ultTwoFactorVerifyTotp((string) $setup['secret'], $code)) {
            $error = 'Code 2FA invalide. Vérifiez Google Authenticator puis réessayez.';
            return;
        }

        $stmt = $bdd->prepare("
            UPDATE user
            SET two_factor_enabled = 1,
                two_factor_secret = ?,
                two_factor_backup_codes = ?,
                two_factor_confirmed_at = NOW()
            WHERE userId = ?
        ");
        $stmt->execute([
            (string) $setup['secret'],
            ultTwoFactorHashBackupCodes((array) $setup['backup_codes']),
            $userId,
        ]);

        unset($_SESSION['2fa_setup']);
        logActivity($bdd, $userId, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'two_factor_enabled', '2FA activée.');
        $success = '2FA activée avec succès.';
        return;
    }

    if (isset($_POST['disable_2fa'])) {
        $confirmation = trim($_POST['disable_2fa_confirmation'] ?? '');
        $user = ultTwoFactorFetchUser($bdd, $userId);

        if (!$user) {
            $error = 'Utilisateur introuvable.';
            return;
        }

        $confirmed = password_verify($confirmation, (string) $user['password'])
            || ultTwoFactorVerifyTotp((string) $user['two_factor_secret'], $confirmation);

        if (!$confirmed) {
            $error = 'Mot de passe ou code 2FA invalide.';
            return;
        }

        $stmt = $bdd->prepare("
            UPDATE user
            SET two_factor_enabled = 0,
                two_factor_secret = NULL,
                two_factor_backup_codes = NULL,
                two_factor_confirmed_at = NULL
            WHERE userId = ?
        ");
        $stmt->execute([$userId]);

        unset($_SESSION['2fa_setup']);
        logActivity($bdd, $userId, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'two_factor_disabled', '2FA désactivée.');
        $success = '2FA désactivée avec succès.';
    }
}

function ultTwoFactorProfileState(PDO $bdd): array
{
    $user = ultTwoFactorFetchUser($bdd, (int) ($_SESSION['userId'] ?? 0));
    $setup = $_SESSION['2fa_setup'] ?? null;
    $qrCode = '';

    if ($user && is_array($setup) && !empty($setup['secret'])) {
        $label = ($user['email'] ?: $user['fullname']) . ' - ' . $user['role'];
        $qrCode = ultTwoFactorAuth()->getQRCodeImageAsDataUri($label, (string) $setup['secret'], 240);
    }

    return [
        'user' => $user,
        'setup' => is_array($setup) ? $setup : null,
        'qr_code' => $qrCode,
    ];
}
