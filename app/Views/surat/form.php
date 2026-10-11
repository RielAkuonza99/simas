<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';

$f = $formData;
$backUrl = url($editing ? 'surat/' . (int) $id : 'surat');
?>
<a class="crumb" href="<?= e($backUrl) ?>"><?= ui_icon('arrow-left') ?><?= $editing ? 'Kembali ke detail surat' : 'Kembali ke daftar' ?></a>
<div class="page-head">
    <div class="page-head__main">
        <h2 class="page-title"><?= $editing ? 'Edit' : 'Tambah' ?> Surat Masuk</h2>
        <p class="page-sub">No agenda dan ID pencarian dibuat saat surat disimpan.</p>
    </div>
</div>

<?= ui_error_summary($errors, 'Data belum dapat disimpan:') ?>

<form method="post" action="<?= e(url($editing ? 'surat/' . (int) $id . '/edit' : 'surat/tambah')) ?>" class="card form-card doc-form" data-validate>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

    <section class="form-section" aria-labelledby="sec-info">
        <div>
            <h3 class="form-section__title" id="sec-info">Informasi Surat</h3>
            <p class="form-section__desc">Identitas surat sesuai dokumen. Kolom bertanda <span class="req" aria-hidden="true">*</span> wajib diisi.</p>
        </div>
        <div class="form-section__body">
            <?php if ($editing): ?>
                <dl class="readonly-strip">
                    <div><dt>No Agenda</dt><dd><?= (int) $surat['no'] ?></dd></div>
                    <div><dt>ID Pencarian</dt><dd class="code"><?= e($surat['kode_pencarian']) ?></dd></div>
                </dl>
            <?php endif; ?>
            <div class="form-grid">
                <div class="field c-8">
                    <label class="label" for="surat_dari">Surat Dari</label>
                    <input id="surat_dari" name="surat_dari" class="input" maxlength="255" value="<?= e($f['surat_dari']) ?>">
                </div>
                <div class="field c-4">
                    <label class="label" for="sifat">Sifat</label>
                    <select id="sifat" name="sifat" class="select">
                        <?php if (!in_array($f['sifat'], $sifatOptions, true)): ?>
                            <option value="" selected><?= $f['sifat'] !== '' ? 'Nilai lama tidak tersedia' : 'Pilih sifat surat (opsional)' ?></option>
                        <?php else: ?>
                            <option value="" <?= $f['sifat'] === '' ? 'selected' : '' ?>>Pilih sifat surat (opsional)</option>
                        <?php endif; ?>
                        <?php foreach ($sifatOptions as $option): ?>
                            <option value="<?= e($option) ?>" <?= $f['sifat'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field c-4">
                    <label class="label" for="nomor_surat">Nomor Surat</label>
                    <input id="nomor_surat" name="nomor_surat" class="input" maxlength="100" value="<?= e($f['nomor_surat']) ?>">
                </div>
                <div class="field c-4 c-date-half">
                    <label class="label" for="tanggal_surat">TGL Surat</label>
                    <input id="tanggal_surat" type="date" name="tanggal_surat" class="input" value="<?= e($f['tanggal_surat']) ?>">
                </div>
                <div class="field c-4 c-date-half">
                    <label class="label" for="tanggal_masuk">TGL Diterima <span class="req" aria-hidden="true">*</span></label>
                    <input id="tanggal_masuk" type="date" name="tanggal_masuk" class="input" value="<?= e($f['tanggal_masuk']) ?>" required>
                </div>
            </div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="sec-isi">
        <div>
            <h3 class="form-section__title" id="sec-isi">Isi Surat</h3>
            <p class="form-section__desc">Pengusul, perihal, dan catatan internal.</p>
        </div>
        <div class="form-section__body">
            <div class="form-grid">
                <div class="field c-12">
                    <label class="label" for="pengusul">Pengusul</label>
                    <input id="pengusul" name="pengusul" class="input" maxlength="255" value="<?= e($f['pengusul']) ?>">
                </div>
                <div class="field c-12">
                    <label class="label" for="hal">Hal <span class="req" aria-hidden="true">*</span></label>
                    <textarea id="hal" name="hal" class="textarea" rows="4" required><?= e($f['hal']) ?></textarea>
                </div>
                <div class="field c-12">
                    <label class="label" for="catatan">Catatan</label>
                    <textarea id="catatan" name="catatan" class="textarea" rows="2"><?= e($f['catatan']) ?></textarea>
                </div>
            </div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="sec-dokumen">
        <div>
            <h3 class="form-section__title" id="sec-dokumen">Dokumen</h3>
            <p class="form-section__desc">Rujukan ke dokumen asli di Google Drive.</p>
        </div>
        <div class="form-section__body">
            <div class="field">
                <label class="label" for="link_drive">Link Google Drive</label>
                <input id="link_drive" type="url" name="link_drive" class="input" maxlength="500" placeholder="https://drive.google.com/..." value="<?= e($f['link_drive']) ?>">
                <div class="field-hint">Link menjadi rujukan dokumen asli. Gunakan URL https dari drive.google.com atau docs.google.com.</div>
            </div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="sec-disposisi">
        <div>
            <h3 class="form-section__title" id="sec-disposisi">Disposisi</h3>
            <p class="form-section__desc">3 tahap awal, maksimal 5.</p>
        </div>
        <div class="form-section__body">
            <div class="steps" data-disposition-list>
            <?php foreach ($f['disposisi'] as $tahap => $d): ?>
                <div class="step" data-disposition-step>
                    <div class="step__head">
                        <h4 class="step__title" data-disposition-title data-step="<?= (int) $tahap ?>">Disposisi <?= (int) $tahap ?></h4>
                        <button class="btn btn-quiet-danger btn-sm" type="button" data-remove-disposition aria-label="Hapus disposisi <?= (int) $tahap ?>"><?= ui_icon('trash') ?>Hapus tahap</button>
                    </div>
                    <div class="form-grid">
                        <div class="field c-4">
                            <label class="label" for="d<?= $tahap ?>_asal" data-disposition-label="asal">Asal</label>
                            <input id="d<?= $tahap ?>_asal" data-disposition-field="asal" name="disposisi[<?= $tahap ?>][asal]" class="input" maxlength="150" value="<?= e($d['asal']) ?>">
                        </div>
                        <div class="field c-4">
                            <label class="label" for="d<?= $tahap ?>_tujuan" data-disposition-label="tujuan">Tujuan</label>
                            <input id="d<?= $tahap ?>_tujuan" data-disposition-field="tujuan" name="disposisi[<?= $tahap ?>][tujuan]" class="input" maxlength="150" value="<?= e($d['tujuan']) ?>">
                        </div>
                        <div class="field c-4">
                            <label class="label" for="d<?= $tahap ?>_tanggal" data-disposition-label="tanggal">Tanggal</label>
                            <input id="d<?= $tahap ?>_tanggal" data-disposition-field="tanggal" type="date" name="disposisi[<?= $tahap ?>][tanggal]" class="input" value="<?= e($d['tanggal']) ?>">
                        </div>
                        <div class="field c-12">
                            <label class="label" for="d<?= $tahap ?>_catatan" data-disposition-label="catatan">Catatan</label>
                            <textarea id="d<?= $tahap ?>_catatan" data-disposition-field="catatan" name="disposisi[<?= $tahap ?>][catatan]" class="textarea" rows="2"><?= e($d['catatan']) ?></textarea>
                        </div>
                        <div class="field c-12">
                            <label class="label" for="d<?= $tahap ?>_lampiran" data-disposition-label="link_lampiran">Lampiran (tautan Google Drive)</label>
                            <input id="d<?= $tahap ?>_lampiran" data-disposition-field="link_lampiran" type="url" name="disposisi[<?= $tahap ?>][link_lampiran]" class="input" maxlength="500" placeholder="https://drive.google.com/..." value="<?= e($d['link_lampiran']) ?>">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <div class="steps-foot">
                <button class="btn btn-secondary" type="button" data-add-disposition><?= ui_icon('plus') ?>Tambah disposisi</button>
                <span class="steps-foot__count" data-disposition-count role="status" aria-live="polite"></span>
            </div>
            <template data-disposition-template>
                <div class="step" data-disposition-step>
                    <div class="step__head">
                        <h4 class="step__title" data-disposition-title data-step=""></h4>
                        <button class="btn btn-quiet-danger btn-sm" type="button" data-remove-disposition><?= ui_icon('trash') ?>Hapus tahap</button>
                    </div>
                    <div class="form-grid">
                        <div class="field c-4">
                            <label class="label" data-disposition-label="asal">Asal</label>
                            <input class="input" data-disposition-field="asal" maxlength="150">
                        </div>
                        <div class="field c-4">
                            <label class="label" data-disposition-label="tujuan">Tujuan</label>
                            <input class="input" data-disposition-field="tujuan" maxlength="150">
                        </div>
                        <div class="field c-4">
                            <label class="label" data-disposition-label="tanggal">Tanggal</label>
                            <input type="date" class="input" data-disposition-field="tanggal">
                        </div>
                        <div class="field c-12">
                            <label class="label" data-disposition-label="catatan">Catatan</label>
                            <textarea class="textarea" data-disposition-field="catatan" rows="2"></textarea>
                        </div>
                        <div class="field c-12">
                            <label class="label" data-disposition-label="link_lampiran">Lampiran (tautan Google Drive)</label>
                            <input type="url" class="input" data-disposition-field="link_lampiran" maxlength="500" placeholder="https://drive.google.com/...">
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </section>

    <div class="form-actions">
        <span class="form-note"><span class="req" aria-hidden="true">*</span> wajib diisi</span>
        <a href="<?= e($backUrl) ?>" class="btn btn-secondary">Batal</a>
        <button class="btn btn-primary" type="submit" data-loading-text="Menyimpan…"><?= $editing ? 'Simpan Perubahan' : 'Simpan Surat' ?></button>
    </div>
</form>
<?php require APP_ROOT . '/includes/footer.php'; ?>
