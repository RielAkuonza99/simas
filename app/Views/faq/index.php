<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';
?>
<div class="page-head">
    <div class="page-head__main">
        <h2 class="page-title">Panduan dan Feedback</h2>
        <p class="page-sub">Panduan ringkas untuk <?= e($roleLabel) ?>, pencarian surat, dan bantuan developer.</p>
    </div>
</div>
<?= ui_alert($notice, true) ?>

<nav class="faq-jump" aria-label="Bagian panduan">
    <a href="#panduan-akses">Panduan <?= e($roleLabel) ?></a>
    <a href="#panduan-umum">Pertanyaan umum</a>
    <a href="#feedback-dev">Feedback untuk developer</a>
</nav>

<section class="faq-section" id="panduan-akses" aria-labelledby="faq-role-heading">
    <div class="faq-section__head">
        <span class="faq-role-label"><?= e($roleLabel) ?></span>
        <h3 id="faq-role-heading">Panduan sesuai akses</h3>
    </div>
    <div class="faq-topics">
        <?php foreach ($roleTopics as $topicIndex => $topic): ?>
            <section class="faq-topic" aria-labelledby="faq-role-topic-<?= (int) $topicIndex ?>">
                <h4 id="faq-role-topic-<?= (int) $topicIndex ?>"><?= e($topic['title']) ?></h4>
                <div class="faq-list">
                    <?php foreach ($topic['questions'] as [$question, $answer]): ?>
                        <details class="faq-item">
                            <summary><?= e($question) ?></summary>
                            <div class="faq-item__answer"><p><?= e($answer) ?></p></div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</section>

<section class="faq-section" id="panduan-umum" aria-labelledby="faq-common-heading">
    <div class="faq-section__head"><h3 id="faq-common-heading">Pertanyaan umum</h3></div>
    <div class="faq-topics">
        <?php foreach ($commonTopics as $topicIndex => $topic): ?>
            <section class="faq-topic" aria-labelledby="faq-common-topic-<?= (int) $topicIndex ?>">
                <h4 id="faq-common-topic-<?= (int) $topicIndex ?>"><?= e($topic['title']) ?></h4>
                <div class="faq-list">
                    <?php foreach ($topic['questions'] as [$question, $answer]): ?>
                        <details class="faq-item">
                            <summary><?= e($question) ?></summary>
                            <div class="faq-item__answer"><p><?= e($answer) ?></p></div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</section>

<section class="faq-section feedback-section" id="feedback-dev" aria-labelledby="feedback-heading">
    <div class="faq-section__head">
        <div>
            <h3 id="feedback-heading">Feedback untuk developer</h3>
            <p class="page-sub">Laporkan bug atau sampaikan usulan. Masukan diteruskan melalui email.</p>
        </div>
    </div>
    <form class="card card__body feedback-form" method="post" action="<?= e(url('panduan/feedback')) ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <div class="feedback-fields">
            <div class="field">
                <label class="label" for="feedback-name">Dari</label>
                <input class="input" id="feedback-name" name="sender_name" maxlength="120" autocomplete="name" value="<?= e(currentUser()['username'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label class="label" for="feedback-email">Email pengirim</label>
                <input class="input" id="feedback-email" name="sender_email" type="email" maxlength="254" autocomplete="email" required>
            </div>
            <div class="field">
                <label class="label" for="feedback-whatsapp">Nomor WhatsApp</label>
                <input class="input" id="feedback-whatsapp" name="whatsapp" type="tel" inputmode="tel" maxlength="32" autocomplete="tel" placeholder="Contoh: +62 812 3456 7890" required>
            </div>
            <div class="field feedback-photo-field" data-feedback-photos data-max-files="5" data-max-bytes="5242880" data-max-total="12582912">
                <label class="label" for="feedback-photo">Foto atau screenshot (opsional, maksimal 5)</label>
                <input class="input" id="feedback-photo" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
                <small class="field-hint">JPEG, PNG, atau WebP. Maksimal 5 foto, 5 MB per foto, total 12 MB. Foto besar dikecilkan otomatis.</small>
                <ul class="feedback-photo-list" data-photo-list aria-live="polite"></ul>
            </div>
            <div class="field feedback-message-field">
                <label class="label" for="feedback-message">Keterangan lengkap</label>
                <textarea class="textarea" id="feedback-message" name="message" rows="6" minlength="20" maxlength="8000" placeholder="Jelaskan halaman yang dibuka, langkah yang dilakukan, dan hasil yang muncul." required></textarea>
            </div>
        </div>
        <p class="feedback-privacy">Jangan sertakan kata sandi atau lampirkan surat dan data pribadi yang bersifat rahasia.</p>
        <div class="feedback-actions">
            <button class="btn btn-primary" type="submit">Kirim feedback</button>
        </div>
    </form>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>