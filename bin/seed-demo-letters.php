<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (APP_ENVIRONMENT !== 'local') {
    fwrite(STDERR, "Seed demo hanya boleh dijalankan pada APP_ENV=local.\n");
    exit(2);
}

try {
    $pdo = App\Database\Connection::get();
    $existingDemo = (int) $pdo->query("SELECT COUNT(*) FROM surat_masuk WHERE hal LIKE 'DEMO-%'")->fetchColumn();
    if ($existingDemo > 0) {
        fwrite(STDERR, "Data demo sudah ada ({$existingDemo} surat); tidak ada perubahan.\n");
        exit(2);
    }

    $adminId = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
    if ($adminId === false) {
        throw new RuntimeException('Akun admin aktif diperlukan untuk membuat data demo.');
    }
    $startNumber = (int) $pdo->query('SELECT COALESCE(MAX(no), 0) + 1 FROM surat_masuk')->fetchColumn();
    $pdo->beginTransaction();

    $letter = $pdo->prepare(
        'INSERT INTO surat_masuk
            (no, tanggal_masuk, nomor_surat, tanggal_surat, sifat, surat_dari, hal,
             selesai_tahap, selesai_oleh, selesai_pada, created_by)
         VALUES (:no, :tanggal_masuk, :nomor_surat, :tanggal_surat, :sifat, :surat_dari, :hal,
             :selesai_tahap, :selesai_oleh, :selesai_pada, :created_by)'
    );
    $searchId = $pdo->prepare('UPDATE surat_masuk SET kode_pencarian = :kode WHERE id = :id');
    $disposition = $pdo->prepare(
        'INSERT INTO surat_disposisi (surat_id, tahap, asal, tujuan, tanggal, catatan)
         VALUES (:surat_id, :tahap, :asal, :tujuan, :tanggal, :catatan)'
    );
    $baseDate = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    $natures = ['Rahasia', 'Segera', 'Sangat Segera'];
    $targets = ['Kepala, Seketariat', 'Kasubbag umum, Seketariat', 'Kepala'];

    for ($index = 1; $index <= 100; $index++) {
        $receivedDate = $baseDate->modify('-' . ($index - 1) . ' days');
        $completedStage = $index % 2 === 0 ? (($index - 2) % 5) + 1 : 0;
        $letter->execute([
            'no' => $startNumber + $index - 1,
            'tanggal_masuk' => $receivedDate->format('Y-m-d'),
            'nomor_surat' => sprintf('DEMO/%03d', $index),
            'tanggal_surat' => $receivedDate->modify('-2 days')->format('Y-m-d'),
            'sifat' => $natures[($index - 1) % count($natures)],
            'surat_dari' => sprintf('Instansi Demo %02d', (($index - 1) % 12) + 1),
            'hal' => sprintf('DEMO-%03d - Surat uji struktur agenda dan disposisi.', $index),
            'selesai_tahap' => $completedStage,
            'selesai_oleh' => $completedStage > 0 ? (int) $adminId : null,
            'selesai_pada' => $completedStage > 0 ? $receivedDate->setTime(9, 0)->format('Y-m-d H:i:s') : null,
            'created_by' => (int) $adminId,
        ]);
        $suratId = (int) $pdo->lastInsertId();
        $searchId->execute(['kode' => 'BAPPERIDA-' . str_pad((string) $suratId, 4, '0', STR_PAD_LEFT), 'id' => $suratId]);

        $lastStage = $completedStage > 0 ? $completedStage : (($index % 3) + 1);
        for ($stage = 1; $stage <= $lastStage; $stage++) {
            $disposition->execute([
                'surat_id' => $suratId,
                'tahap' => $stage,
                'asal' => sprintf('Pejabat Demo %03d', $index),
                'tujuan' => $targets[($stage - 1) % count($targets)],
                'tanggal' => $receivedDate->modify('+' . $stage . ' days')->format('Y-m-d'),
                'catatan' => 'Catatan uji disposisi tahap ' . $stage,
            ]);
        }
    }

    $sequence = $pdo->prepare('UPDATE surat_sequences SET next_no = :next_no WHERE id = 1');
    $sequence->execute(['next_no' => $startNumber + 100]);
    $pdo->commit();
    echo "100 surat demo berhasil dibuat. Agenda berikutnya: B-" . str_pad((string) ($startNumber + 100), 4, '0', STR_PAD_LEFT) . PHP_EOL;
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}