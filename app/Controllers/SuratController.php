<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Models\SuratModel;
use App\Models\AdminInboxModel;
use App\Security\AuditLogger;
use App\Services\SimpleXlsxWriter;
use App\Services\SuratExportFormatter;
use App\Services\SuratService;

final class SuratController
{
    private const PDF_MAX_ROWS = 500;

    private SuratService $surat;

    public function __construct()
    {
        $pdo = Connection::get();
        $this->surat = new SuratService(new SuratModel($pdo), new AuditLogger($pdo));
    }

    public function index(): void
    {
        AuthMiddleware::requireLogin();
        $filters = [
            'q' => mb_substr(trim($this->queryString('q')), 0, 200),
            'dari' => $this->filterDate($this->queryString('dari')),
            'sampai' => $this->filterDate($this->queryString('sampai')),
        ];
        $pageValue = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $result = $this->surat->search($filters, $pageValue !== false && $pageValue > 0 ? $pageValue : 1);
        $approvedEditSuratIds = [];
        if ((currentUser()['role'] ?? '') === 'pelayanan') {
            $approvedEditSuratIds = array_fill_keys(
                (new AdminInboxModel(Connection::get()))->approvedEditSuratIds(
                    array_column($result['items'], 'id'),
                    (int) currentUser()['id']
                ),
                true
            );
        }
        foreach ($result['items'] as &$item) {
            $item['driveUrl'] = $this->surat->safeDriveUrl($item['link_drive'] ?? null);
        }
        unset($item);
        render('surat/index', [
            'pageTitle' => 'Surat Masuk',
            'filters' => $filters,
            'data' => $result['items'],
            'approvedEditSuratIds' => $approvedEditSuratIds,
            'pagination' => $result,
            'notice' => $_SESSION['flash'] ?? null,
            'savedSuratId' => (int) ($_SESSION['saved_surat_id'] ?? 0),
        ]);
        unset($_SESSION['flash'], $_SESSION['saved_surat_id']);
    }

    public function dispositionHistory(): void
    {
        AuthMiddleware::requireLogin();
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $history = (new SuratModel(Connection::get()))->dispositionHistory($page !== false && $page > 0 ? $page : 1);
        render('disposition-history/index', [
            'pageTitle' => 'Riwayat Disposisi',
            'history' => $history,
        ]);
    }
    
    public function bulkDelete(): void
    {
        AuthMiddleware::requireAdmin();
        requirePost();
        validateCsrf();
        $submitted = $_POST['ids'] ?? null;
        if (!is_array($submitted) || $submitted === [] || count($submitted) > 100) {
            http_response_code(400);
            exit('Pilih 1 sampai 100 surat untuk diproses.');
        }
        $ids = [];
        foreach ($submitted as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                http_response_code(400);
                exit('Daftar surat terpilih tidak valid.');
            }
            $ids[] = $id;
        }
        
        $deleted = $this->surat->deleteMany($ids);
        $_SESSION['flash'] = ['type' => 'success', 'message' => count($deleted) . ' surat terpilih berhasil dihapus.'];
        redirect('surat');
    }
    
    public function exportXlsx(): void
    {
        AuthMiddleware::requireAdmin();
        requirePost();
        validateCsrf();
        [$rows, $rangeLabel, $filename] = $this->exportSelection();
        if (!class_exists(\ZipArchive::class)) {
            http_response_code(500);
            exit('Ekstensi ZIP PHP belum aktif di server ini. Gunakan ekspor PDF.');
        }

        $summary = SuratExportFormatter::summary($rows);
        $numbered = [];
        foreach ($rows as $index => $row) {
            $numbered[] = SuratExportFormatter::row($row, $index + 1);
        }
        $path = (new SimpleXlsxWriter('Surat Masuk'))->save(
            SuratExportFormatter::columns(),
            $numbered,
            [
                'title' => 'Laporan Surat Masuk',
                'lines' => [
                    'BAPPERIDA · Periode: ' . $rangeLabel,
                    'Dibuat pada ' . date('d/m/Y H:i'),
                    implode('  ·  ', array_map(
                        static fn (string $label, int $count): string => $label . ': ' . $count,
                        array_keys($summary),
                        $summary
                    )),
                ],
                'empty' => 'Tidak ada surat untuk periode atau ID yang dipilih.',
            ]
        );
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        @unlink($path);
        exit;
    }

    public function exportPdf(): void
    {
        AuthMiddleware::requireAdmin();
        requirePost();
        validateCsrf();
        [$rows, $rangeLabel, $filename] = $this->exportSelection();
        if (count($rows) > self::PDF_MAX_ROWS) {
            http_response_code(413);
            exit('Terlalu banyak surat untuk satu PDF (' . count($rows) . '; maksimal ' . self::PDF_MAX_ROWS . '). Pilih periode yang lebih sempit atau gunakan ekspor Excel.');
        }
        require_once APP_ROOT . '/vendor/autoload.php';

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->setChroot(APP_ROOT);
        $pdf = new \Dompdf\Dompdf($options);
        ob_start();
        render('surat/export-pdf', [
            'rows' => $rows,
            'rangeLabel' => $rangeLabel,
            'generatedAt' => date('d/m/Y H:i'),
        ]);
        $html = (string) ob_get_clean();
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $footerY = $canvas->get_height() - 24;
        $canvas->page_text(22, $footerY, 'Laporan Surat Masuk BAPPERIDA · ' . mb_strimwidth($rangeLabel, 0, 90, '…', 'UTF-8'), $font, 7, [0.4, 0.45, 0.5]);
        $canvas->page_text($canvas->get_width() - 100, $footerY, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', $font, 7, [0.4, 0.45, 0.5]);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        echo $pdf->output();
        exit;
    }

    public function searchExportCandidate(): void
    {
        AuthMiddleware::requireAdmin();
        $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'ID surat tidak valid.'], JSON_THROW_ON_ERROR);
            return;
        }

        $letter = (new SuratModel(Connection::get()))->findExportCandidate((int) $id);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        if ($letter === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Surat tidak ditemukan.'], JSON_THROW_ON_ERROR);
            return;
        }

        echo json_encode(['item' => $letter], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @return array{0: list<array>, 1: string, 2: string} baris, label periode, nama berkas tanpa ekstensi */
    private function exportSelection(): array
    {
        $range = is_string($_POST['range'] ?? null) ? $_POST['range'] : 'week';
        if (!in_array($range, ['week', 'month', 'all'], true)) {
            http_response_code(400);
            exit('Rentang ekspor tidak valid.');
        }

        $rawIds = $_POST['selected_ids'] ?? [];
        if (!is_array($rawIds)) {
            http_response_code(400);
            exit('Pilihan ID surat tidak valid.');
        }
        $ids = [];
        foreach ($rawIds as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id === false || $id === null || $id < 1) {
                http_response_code(400);
                exit('Pilihan ID surat tidak valid.');
            }
            $ids[] = (int) $id;
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) > 500) {
            http_response_code(400);
            exit('Ekspor pilihan dibatasi maksimal 500 surat.');
        }

        $fromDate = null;
        $throughDate = null;
        $rangeLabel = 'Seluruh surat';
        $slug = 'seluruhnya';
        if ($range === 'week') {
            $anchor = $this->exportDate($_POST['week_date'] ?? null, 'Tanggal minggu tidak valid.');
            $monday = $anchor->modify('monday this week');
            $sunday = $monday->modify('+6 days');
            $fromDate = $monday->format('Y-m-d');
            $throughDate = $sunday->format('Y-m-d');
            $rangeLabel = 'Minggu ' . SuratExportFormatter::periodLabel($fromDate, $throughDate);
            $slug = 'minggu-' . $monday->format('o') . '-w' . $monday->format('W');
        } elseif ($range === 'month') {
            $monthNumber = $this->exportInt($_POST['month_m'] ?? null, 1, 12, (int) date('n'), 'Bulan ekspor tidak valid.');
            $yearNumber = $this->exportInt($_POST['month_y'] ?? null, 2000, 2100, (int) date('Y'), 'Tahun ekspor tidak valid.');
            $value = sprintf('%04d-%02d', $yearNumber, $monthNumber);
            $match = [$value, (string) $yearNumber, (string) $monthNumber];
            $first = new \DateTimeImmutable($value . '-01');
            $fromDate = $first->format('Y-m-d');
            $throughDate = $first->modify('last day of this month')->format('Y-m-d');
            $rangeLabel = SuratExportFormatter::monthLabel((int) $match[2], (int) $match[1]);
            $slug = 'bulan-' . $value;
        }
        if ($ids !== []) {
            $fromDate = null;
            $throughDate = null;
            $rangeLabel = 'Pilihan ID: ' . implode(', ', $ids);
            $slug = 'pilihan';
        }

        $rows = (new SuratModel(Connection::get()))->exportRows($fromDate, $throughDate, $ids);

        return [$rows, $rangeLabel, 'surat-masuk-' . $slug . '-' . date('Ymd')];
    }

    private function exportInt(mixed $value, int $min, int $max, int $default, string $error): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($number === false) {
            http_response_code(400);
            exit($error);
        }

        return $number;
    }

    private function exportDate(mixed $value, string $error): \DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return new \DateTimeImmutable('today');
        }
        $date = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if ($date === false || $date->format('Y-m-d') !== $value) {
            http_response_code(400);
            exit($error);
        }

        return $date;
    }

    public function create(): void
    {
        AuthMiddleware::requireSuratCreator();
        $errors = [];
        $formData = $this->surat->emptyForm();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            requirePost();
            validateCsrf();
            [$formData, $errors] = $this->surat->validate($_POST);
            if (!$errors) {
                $_SESSION['saved_surat_id'] = $this->surat->create($formData);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Surat berhasil disimpan.'];
                redirect('surat');
            }
        }

        render('surat/create', [
            'pageTitle' => 'Tambah Surat',
            'errors' => $errors,
            'formData' => $formData,
            'surat' => null,
            'sifatOptions' => SuratService::SIFAT,
        ]);
    }

    public function show(): void
    {
        AuthMiddleware::requireLogin();
        $id = $this->requestId();
        $surat = $this->surat->find($id);
        if (!$surat) {
            http_response_code(404);
            exit('Data tidak ditemukan.');
        }
        foreach ($surat['disposisi'] as &$disposisi) {
            $disposisi['driveUrl'] = $this->surat->safeDriveUrl($disposisi['link_lampiran'] ?? null);
        }
        unset($disposisi);

        render('surat/show', [
            'pageTitle' => 'Detail Surat',
            'surat' => $surat,
            'id' => $id,
            'driveUrl' => $this->surat->safeDriveUrl($surat['link_drive'] ?? null),
            'completionDriveUrl' => $this->surat->safeDriveUrl($surat['link_selesai'] ?? null),
            'notice' => $_SESSION['flash'] ?? null,
        ]);
        unset($_SESSION['flash']);
    }

    public function edit(): void
    {
        AuthMiddleware::requireSuratEditor();
        $id = $this->requestId();
        $surat = $this->surat->find($id);
        if (!$surat) {
            http_response_code(404);
            exit('Data tidak ditemukan.');
        }

        $isAdmin = (currentUser()['role'] ?? '') === 'admin';
        $inbox = new AdminInboxModel(Connection::get());
        $canEdit = $isAdmin || $inbox->hasApprovedEditAccess($id, (int) currentUser()['id']);
        if (!$canEdit) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                requirePost();
                validateCsrf();
                http_response_code(403);
                exit('Persetujuan admin untuk surat ini belum tersedia.');
            }
            render('surat/edit-request', [
                'pageTitle' => 'Minta Akses Edit Surat',
                'surat' => $surat,
                'request' => $inbox->latestEditRequest($id, (int) currentUser()['id']),
                'id' => $id,
            ]);
            return;
        }

        $errors = [];
        $formData = $this->surat->formFromRecord($surat);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            requirePost();
            validateCsrf();
            [$formData, $errors] = $this->surat->validate($_POST);
            if (!$errors) {
                try {
                    $this->surat->update($id, $formData);
                } catch (\DomainException $error) {
                    $_SESSION['flash'] = ['type' => 'warning', 'message' => $error->getMessage()];
                    redirect('surat/' . $id . '/edit');
                }
                redirect('surat/' . $id);
            }
        }

        render('surat/edit', [
            'pageTitle' => 'Edit Surat',
            'surat' => $surat,
            'id' => $id,
            'errors' => $errors,
            'formData' => $formData,
            'sifatOptions' => SuratService::SIFAT,
        ]);
    }

    public function requestEdit(): void
    {
        AuthMiddleware::requireSuratEditor();
        requirePost();
        validateCsrf();
        if ((currentUser()['role'] ?? '') !== 'pelayanan') {
            http_response_code(403);
            exit('Pengajuan akses edit hanya tersedia untuk Pelayanan.');
        }
        $id = $this->requestId();
        if (!$this->surat->find($id)) {
            http_response_code(404);
            exit('Data tidak ditemukan.');
        }
        $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
        if ($message === '' || mb_strlen($message) > 500) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Alasan pengajuan wajib diisi dan maksimal 500 karakter.'];
            redirect('surat/' . $id . '/edit');
        }

        $pdo = Connection::get();
        $inbox = new AdminInboxModel($pdo);
        if ($inbox->hasApprovedEditAccess($id, (int) currentUser()['id'])) {
            $_SESSION['flash'] = ['type' => 'info', 'message' => 'Persetujuan edit masih berlaku. Anda dapat langsung mengedit surat ini.'];
            redirect('surat/' . $id . '/edit');
        }
        $inbox->createRequest('EDIT_ACCESS', $id, (int) currentUser()['id'], $message);
        (new AuditLogger($pdo))->record('SURAT_EDIT_REQUESTED', 'surat_masuk', $id, (int) currentUser()['id']);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Permintaan edit dikirim ke administrator.'];
        redirect('surat/' . $id . '/edit');
    }

    public function submitRequest(): void
    {
        AuthMiddleware::requireSuratEditor();
        requirePost();
        validateCsrf();
        if ((currentUser()['role'] ?? '') !== 'pelayanan') {
            http_response_code(403);
            exit('Pengajuan hanya tersedia untuk Pelayanan.');
        }
        $id = $this->requestId();
        if (!$this->surat->find($id)) {
            http_response_code(404);
            exit('Data tidak ditemukan.');
        }
        $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
        if ($message === '' || mb_strlen($message) > 500) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Isi pengajuan wajib diisi dan maksimal 500 karakter.'];
            redirect('surat/' . $id);
        }

        $pdo = Connection::get();
        (new AdminInboxModel($pdo))->createRequest('MESSAGE', $id, (int) currentUser()['id'], $message);
        (new AuditLogger($pdo))->record('SURAT_SUBMISSION_SENT', 'surat_masuk', $id, (int) currentUser()['id']);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Pengajuan dikirim ke administrator.'];
        redirect('surat/' . $id);
    }

    public function delete(): void
    {
        AuthMiddleware::requireAdmin();
        requirePost();
        validateCsrf();
        $id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id !== false && $id !== null && $id > 0) {
            $this->surat->delete($id);
        }
        redirect('surat');
    }

    private function requestId(): int
    {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

        return $id !== false && $id !== null && $id > 0 ? $id : 0;
    }

    private function queryString(string $key): string
    {
        $value = $_GET[$key] ?? '';

        return is_string($value) ? $value : '';
    }
    
    private function filterDate(string $value): string
    {
        $date = trim($value);
        if ($date === '') {
            return '';
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($parsed === false || $parsed->format('Y-m-d') !== $date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return '';
        }

        return $date;
    }
}