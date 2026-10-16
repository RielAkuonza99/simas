<?php
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/sidebar.php';

$f = $formData;
$backUrl = url($editing ? 'surat/' . (int) $id : 'surat');
$maxTujuan = \App\Services\SuratService::TUJUAN_MAKS;
$tujuanRow = static fn (string $name, string $value): string =>
    '<div class="target-row" data-target-row>'
    . '<input class="input" type="text" list="tujuan-saran" maxlength="' . \App\Services\SuratService::TUJUAN_PANJANG_MAKS . '" autocomplete="off"'
    . ' data-disposition-target="tujuan"' . ($name !== '' ? ' name="' . e($name) . '"' : '')
    . ' value="' . e($value) . '" placeholder="Ketik tujuan disposisi">'
    . '<button class="btn btn-quiet-danger btn-sm" type="button" data-remove-target aria-label="Hapus tujuan">' . ui_icon('trash') . '<span class="btn-label">Hapus</span></button>'
    . '</div>';
?>
<a class="crumb" href="<?= e($backUrl) ?>"><?= ui_icon('arrow-left') ?><?= $editing ? 'Kembali ke detail surat' : 'Kembali ke daftar' ?></a>
<div class="page-head">
    <div class="page-head__main">
        <h2 class="page-title"><?= $editing ? 'Edit' : 'Tambah' ?> Surat Masuk</h2>
        <p class="page-sub">ID surat dan nomor agenda dibuat otomatis saat surat disimpan.</p>
    </div>
</div>

<?= ui_error_summary($errors, 'Data belum dapat disimpan:') ?>

<form method="post" action="<?= e(url($editing ? 'surat/' . (int) $id . '/edit' : 'surat/tambah')) ?>" class="card form-card doc-form" data-validate>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

    <section class="form-section" aria-labelledby="sec-agenda">
        <div>
            <h3 class="form-section__title" id="sec-agenda">Agenda Surat</h3>
            <p class="form-section__desc">Nomor dibuat otomatis. Kolom bertanda <span class="req" aria-hidden="true">*</span> wajib diisi.</p>
        </div>
        <div class="form-section__body">
            <dl class="readonly-strip">
                <div><dt>ID Surat</dt><dd class="code"><?= $editing ? '#' . (int) $surat['id'] : 'Dibuat saat disimpan' ?></dd></div>
                <div><dt>No Agenda</dt><dd class="code"><?= $editing ? 'B-' . str_pad((string) $surat['no'], 4, '0', STR_PAD_LEFT) : 'Dibuat saat disimpan' ?></dd></div>
            </dl>
            <div class="form-grid">
                <div class="field c-6">
                    <label class="label" for="tanggal_masuk">Tanggal Surat Diterima <span class="req" aria-hidden="true">*</span></label>
                    <input id="tanggal_masuk" type="date" name="tanggal_masuk" class="input" value="<?= e($f['tanggal_masuk']) ?>" required>
                </div>
            </div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="sec-info">
        <div>
            <h3 class="form-section__title" id="sec-info">Informasi Surat</h3>
            <p class="form-section__desc">Data sesuai dokumen surat.</p>
        </div>
        <div class="form-section__body">
            <div class="form-grid">
                <div class="field c-8">
                    <label class="label" for="surat_dari">Asal Surat</label>
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
                    <label class="label" for="tanggal_surat">Tanggal Surat</label>
                    <input id="tanggal_surat" type="date" name="tanggal_surat" class="input" value="<?= e($f['tanggal_surat']) ?>">
                </div>
                <div class="field c-12">
                    <label class="label" for="hal">Hal Surat <span class="req" aria-hidden="true">*</span></label>
                    <textarea id="hal" name="hal" class="textarea" rows="4" required><?= e($f['hal']) ?></textarea>
                </div>
                <div class="field c-12">
                <label class="label" for="link_drive">Bukti Dukung</label>
                <input id="link_drive" type="url" name="link_drive" class="input" maxlength="500" placeholder="https://drive.google.com/..." value="<?= e($f['link_drive']) ?>">
                <div class="field-hint">Gunakan URL https dari drive.google.com atau docs.google.com.</div>
                </div>
            </div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="sec-disposisi">
        <div>
            <h3 class="form-section__title" id="sec-disposisi">Disposisi</h3>
            <p class="form-section__desc">Tiga tahap awal, maksimal lima. Ketik tujuan secara manual (maksimal <?= $maxTujuan ?> per tahap); saran muncul saat mengetik.</p>
        </div>
        <div class="form-section__body">
            <datalist id="tujuan-saran">
                <?php foreach (\App\Services\SuratService::TUJUAN_SARAN as $saran): ?><option value="<?= e($saran) ?>"></option><?php endforeach; ?>
            </datalist>
            <template data-target-template><?= $tujuanRow('', '') ?></template>
            <div class="steps" data-disposition-list>
            <?php foreach ($f['disposisi'] as $tahap => $d): ?>
                <div class="step" data-disposition-step>
                    <div class="step__head">
                        <h4 class="step__title" data-disposition-title data-step="<?= (int) $tahap ?>">Disposisi <?= (int) $tahap ?></h4>
                        <button class="btn btn-quiet-danger btn-sm" type="button" data-remove-disposition aria-label="Hapus disposisi <?= (int) $tahap ?>"><?= ui_icon('trash') ?>Hapus tahap</button>
                    </div>
                    <div class="form-grid">
                        <div class="field c-6">
                            <label class="label" for="d<?= $tahap ?>_pejabat" data-disposition-label="pejabat">Pejabat</label>
                            <input id="d<?= $tahap ?>_pejabat" data-disposition-field="pejabat" name="disposisi[<?= $tahap ?>][pejabat]" class="input" maxlength="150" value="<?= e($d['pejabat']) ?>">
                        </div>
                        <div class="field c-6">
                            <label class="label" for="d<?= $tahap ?>_tanggal" data-disposition-label="tanggal">Tanggal</label>
                            <input id="d<?= $tahap ?>_tanggal" data-disposition-field="tanggal" type="date" name="disposisi[<?= $tahap ?>][tanggal]" class="input" value="<?= e($d['tanggal']) ?>">
                        </div>
                        <fieldset class="field c-12 target-options" data-target-group>
                            <legend class="label">Tujuan <span class="label-note">(ketik manual, maksimal <?= $maxTujuan ?>)</span></legend>
                            <div class="target-list" data-target-list>
                                <?php foreach (($d['tujuan'] === [] ? [''] : array_slice($d['tujuan'], 0, $maxTujuan)) as $tujuan): ?>
                                    <?= $tujuanRow('disposisi[' . (int) $tahap . '][tujuan][]', (string) $tujuan) ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="target-foot">
                                <button class="btn btn-secondary btn-sm" type="button" data-add-target><?= ui_icon('plus') ?>Tambah tujuan</button>
                                <span class="field-hint" data-target-count role="status" aria-live="polite"></span>
                            </div>
                        </fieldset>
                        <div class="field c-12">
                            <label class="label" for="d<?= $tahap ?>_catatan" data-disposition-label="catatan">Catatan</label>
                            <textarea id="d<?= $tahap ?>_catatan" data-disposition-field="catatan" name="disposisi[<?= $tahap ?>][catatan]" class="textarea" rows="2"><?= e($d['catatan']) ?></textarea>
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
                        <div class="field c-6">
                            <label class="label" data-disposition-label="pejabat">Pejabat</label>
                            <input class="input" data-disposition-field="pejabat" maxlength="150">
                        </div>
                        <div class="field c-6">
                            <label class="label" data-disposition-label="tanggal">Tanggal</label>
                            <input type="date" class="input" data-disposition-field="tanggal">
                        </div>
                        <fieldset class="field c-12 target-options" data-target-group>
                            <legend class="label">Tujuan <span class="label-note">(ketik manual, maksimal <?= $maxTujuan ?>)</span></legend>
                            <div class="target-list" data-target-list>
                                <?= $tujuanRow('', '') ?>
                            </div>
                            <div class="target-foot">
                                <button class="btn btn-secondary btn-sm" type="button" data-add-target><?= ui_icon('plus') ?>Tambah tujuan</button>
                                <span class="field-hint" data-target-count role="status" aria-live="polite"></span>
                            </div>
                        </fieldset>
                        <div class="field c-12">
                            <label class="label" data-disposition-label="catatan">Catatan</label>
                            <textarea class="textarea" data-disposition-field="catatan" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </template>
            <fieldset class="completion-picker" aria-describedby="completion-hint">
                <legend class="label">Status disposisi</legend>
                <input type="hidden" name="selesai_tahap" value="0">
                <label class="check-option"><input type="radio" name="selesai_tahap" value="0" <?= (int) $f['selesai_tahap'] === 0 ? 'checked' : '' ?>><span>Belum selesai</span></label>
                <?php foreach (\App\Models\SuratModel::DISPOSISI_TAHAP as $tahap): ?>
                    <label class="btn btn-secondary btn-sm completion-option"><input type="radio" name="selesai_tahap" value="<?= (int) $tahap ?>" <?= (int) $f['selesai_tahap'] === (int) $tahap ? 'checked' : '' ?> <?= $tahap > count($f['disposisi']) ? 'disabled' : '' ?>><span>Selesai</span><small>Disposisi <?= (int) $tahap ?></small></label>
                <?php endforeach; ?>
                <p class="field-hint" id="completion-hint">Tandai satu tahap sebagai disposisi akhir.</p>
            </fieldset>
            <div class="field completion-proof" data-completion-proof <?= (int) $f['selesai_tahap'] === 0 ? 'hidden' : '' ?>>
                <label class="label" for="link_selesai">Bukti Dukung Disposisi Akhir</label>
                <input id="link_selesai" type="url" name="link_selesai" class="input" maxlength="500" placeholder="https://drive.google.com/..." value="<?= e($f['link_selesai']) ?>">
                <div class="field-hint">Opsional. Tersedia setelah tahap akhir ditandai selesai.</div>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <span class="form-note"><span class="req" aria-hidden="true">*</span> wajib diisi</span>
        <a href="<?= e($backUrl) ?>" class="btn btn-secondary">Batal</a>
        <button class="btn btn-primary" type="submit" data-loading-text="Menyimpan…"><?= $editing ? 'Simpan Perubahan' : 'Simpan Surat' ?></button>
    </div>
</form>
<?php require APP_ROOT . '/includes/footer.php'; ?>
