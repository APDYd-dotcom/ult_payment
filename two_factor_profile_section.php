<?php
$twoFactorUser = $twoFactorState['user'] ?? null;
$twoFactorSetup = $twoFactorState['setup'] ?? null;
$twoFactorEnabled = $twoFactorUser && (int) ($twoFactorUser['two_factor_enabled'] ?? 0) === 1;
$backupCodes = is_array($twoFactorSetup['backup_codes'] ?? null) ? $twoFactorSetup['backup_codes'] : [];
?>

<div class="profile-card two-factor-card">
    <h3>Authentification à deux facteurs</h3>

    <?php if ($twoFactorEnabled): ?>
        <div class="profile-row">
            <span class="profile-label">Statut</span>
            <span class="profile-value two-factor-status enabled">Activée</span>
        </div>
        <p class="two-factor-help">Votre compte demandera un code Google Authenticator ou un code de secours après le mot de passe.</p>

        <form method="POST" action="profile.php" class="two-factor-form">
            <div class="form-group">
                <label for="disable_2fa_confirmation">Mot de passe ou code 2FA</label>
                <input id="disable_2fa_confirmation" type="password" name="disable_2fa_confirmation" required>
            </div>
            <button type="submit" name="disable_2fa" class="btn btn-danger">Désactiver la 2FA</button>
        </form>
    <?php elseif ($twoFactorSetup): ?>
        <div class="two-factor-setup">
            <div class="two-factor-qr">
                <img src="<?= htmlspecialchars($twoFactorState['qr_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="QR code Google Authenticator">
            </div>
            <div>
                <p class="two-factor-help">Scannez ce QR code avec Google Authenticator, puis saisissez le code à 6 chiffres.</p>
                <p class="two-factor-secret">Secret : <strong><?= htmlspecialchars($twoFactorSetup['secret'], ENT_QUOTES, 'UTF-8') ?></strong></p>
            </div>
        </div>

        <?php if ($backupCodes): ?>
            <div class="backup-codes">
                <p>Codes de secours à conserver maintenant. Ils ne seront plus affichés après activation.</p>
                <div class="backup-code-grid">
                    <?php foreach ($backupCodes as $code): ?>
                        <code><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></code>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="profile.php" class="two-factor-form">
            <div class="form-group">
                <label for="totp_code">Code Google Authenticator</label>
                <input id="totp_code" type="text" name="totp_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
            </div>
            <div class="two-factor-actions">
                <button type="submit" name="confirm_2fa_setup" class="btn btn-primary">Activer la 2FA</button>
                <button type="submit" name="cancel_2fa_setup" class="btn btn-secondary">Annuler</button>
            </div>
        </form>
    <?php else: ?>
        <div class="profile-row">
            <span class="profile-label">Statut</span>
            <span class="profile-value two-factor-status disabled">Désactivée</span>
        </div>
        <p class="two-factor-help">Ajoutez une vérification Google Authenticator après votre mot de passe.</p>
        <form method="POST" action="profile.php">
            <button type="submit" name="start_2fa_setup" class="btn btn-primary">Configurer la 2FA</button>
        </form>
    <?php endif; ?>
</div>
