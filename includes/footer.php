<?php if (isLoggedIn()): ?>
</main>
</div>
</div>
<?php else: ?>
</div>
<?php endif; ?>
<dialog id="confirm-dialog" class="dialog" data-confirm-dialog aria-labelledby="confirm-title" aria-describedby="confirm-message">
    <div class="dialog__body">
        <span class="dialog__icon" data-confirm-icon><?= ui_icon('alert-triangle') ?></span>
        <div>
            <h2 class="dialog__title" id="confirm-title" data-confirm-title>Yakin ingin melanjutkan?</h2>
            <p class="dialog__text" id="confirm-message" data-confirm-text></p>
        </div>
    </div>
    <div class="dialog__actions">
        <button type="button" class="btn btn-secondary" data-confirm-cancel autofocus>Batal</button>
        <button type="button" class="btn btn-danger" data-confirm-accept>Lanjutkan</button>
    </div>
</dialog>
<div class="toast-region" data-toast-region aria-live="polite" aria-atomic="false"></div>
<script src="<?= e(asset('js/script.js')) ?>"></script>
</body>
</html>
