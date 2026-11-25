<dialog id="portal-login" class="portal-dialog" aria-labelledby="portal-login-title">
    <form class="portal-login-form" method="dialog">
        <button type="button" class="portal-close" aria-label="Close portal login">×</button>
        <span class="nusha-mark" aria-hidden="true"></span>
        <h2 id="portal-login-title">Portal login</h2>
        <div class="portal-role-options" role="group" aria-label="Portal type">
            <?php foreach (['student' => 'Student', 'class' => 'Class', 'wing' => 'Wing', 'admin' => 'Admin'] as $role => $label): ?>
                <button type="button" data-role="<?= $role ?>" aria-pressed="<?= $role === 'student' ? 'true' : 'false' ?>"><?= $label ?></button>
            <?php endforeach; ?>
        </div>
        <label>Username<input name="username" type="text" required maxlength="40" autocomplete="username" autocapitalize="none" spellcheck="false"></label>
        <label>Password<input name="password" type="password" required maxlength="128" autocomplete="current-password"></label>
        <button type="submit" class="portal-login-submit">Enter Student Portal</button>
        <p class="portal-login-status" role="status"></p>
    </form>
</dialog>
<script src="/portal-login.js" defer></script>
