<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Models\SuratModel;
use App\Models\AdminInboxModel;
use App\Security\AuditLogger;
use App\Services\SuratService;

final class SuratController
{
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
    
    public function exportCsv(): void
    {
        AuthMiddleware::requireAdmin();
        requirePost();
        validateCsrf();
        [$rows, , $filename] = $this->exportSelection();
        $delimiter = ';';
        $headers = [
            'ID Surat', 'Kode Pencarian', 'No Agenda', 'Tanggal Masuk', 'Asal Surat', 'Pengusul',
            'Nomor Surat', 'Tanggal Surat', 'Sifat Surat', 'Hal Surat', 'Catatan Surat', 'Bukti Surat',
            'Status Disposisi', 'Keterangan Status', 'Tahap Akhir', 'Pejabat Akhir', 'Tanggal Akhir',
            'Diselesaikan Pada', 'Dicatat Oleh', 'Bukti Disposisi Akhir', 'Dibuat Oleh', 'Dibuat Pada',
            'Diubah Pada', 'Tahap Disposisi', 'Asal Disposisi', 'Tujuan Disposisi',
            'Tanggal Disposisi', 'Catatan Disposisi', 'Lampiran Disposisi',
        ];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            http_response_code(500);
            exit;
        }
        fwrite($output, "\xEF\xBB\xBFsep={$delimiter}\r\n");
        fputcsv($output, $headers, $delimiter, '"', '\\');
        foreach ($rows as $row) {
            $stage = (int) $row['selesai_tahap'];
            $status = $stage > 0 ? 'Selesai disposisi ' . $stage : 'Belum selesai';
            $description = match (true) {
                $stage > 0 => 'Surat selesai pada tahap ' . $stage
                    . ($row['pejabat_akhir'] ? ' oleh ' . $row['pejabat_akhir'] : '')
                    . ($row['tanggal_akhir'] ? ' tanggal ' . $row['tanggal_akhir'] : '') . '.',
                $row['disposisi'] !== [] => count($row['disposisi']) . ' tahap disposisi tercatat; belum ditandai selesai.',
                default => 'Belum ada disposisi yang dicatat.',
            };
            $values = [
                '#' . (int) $row['id'],
                $row['kode_pencarian'],
                'B-' . str_pad((string) $row['no'], 4, '0', STR_PAD_LEFT),
                $row['tanggal_masuk'],
                $row['surat_dari'],
                $row['pengusul'],
                $row['nomor_surat'],
                $row['tanggal_surat'],
                $row['sifat'],
                $row['hal'],
                $row['catatan'],
                $row['link_drive'],
                $status,
                $description,
                $stage > 0 ? $stage : '',
                $row['pejabat_akhir'],
                $row['tanggal_akhir'],
                $row['selesai_pada'],
                $row['dicatat_oleh'],
                $row['link_selesai'],
                $row['dibuat_oleh'],
                $row['created_at'],
                $row['updated_at'],
            ];
            $dispositions = $row['disposisi'] !== [] ? $row['disposisi'] : [0 => []];
            foreach ($dispositions as $number => $disposition) {
                $stageValues = array_merge($values, [
                    (int) ($disposition['tahap'] ?? $number) ?: '',
                    $disposition['asal'] ?? '',
                    $disposition['tujuan'] ?? '',
                    $disposition['tanggal'] ?? '',
                    $disposition['catatan'] ?? '',
                    $disposition['link_lampiran'] ?? '',
                ]);
                fputcsv(
                    $output,
                    array_map(fn (mixed $value): string => $this->safeCsvCell($value), $stageValues),
                    $delimiter,
                    '"',
                    '\\'
                );
            }
        }
        fclose($output);
        exit;
    }

    public function exportPdf(): void
    {
        AuthMiddleware::requireAdmin();
        requirePost();
        validateCsrf();
        [$rows, $rangeLabel, $filename] = $this->exportSelection();
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
            'generatedAt' => date('Y-m-d H:i:s'),
        ]);
        $html = (string) ob_get_clean();
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

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

    private function exportSelection(): array
    {
        $range = is_string($_POST['range'] ?? null) ? $_POST['range'] : 'all';
        if (!in_array($range, ['week', 'month', 'year', 'all'], true)) {
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

        $today = new \DateTimeImmutable('today');
        $fromDate = match ($range) {
            'week' => $today->modify('monday this week')->format('Y-m-d'),
            'month' => $today->modify('first day of this month')->format('Y-m-d'),
            'year' => $today->setDate((int) $today->format('Y'), 1, 1)->format('Y-m-d'),
            default => null,
        };
        $throughDate = $range === 'all' || $ids !== [] ? null : $today->format('Y-m-d');
        $rangeLabels = ['week' => 'Minggu ini', 'month' => 'Bulan ini', 'year' => 'Tahun ini', 'all' => 'Seluruh surat'];
        $rangeLabel = $ids === []
            ? $rangeLabels[$range]
            : 'Pilihan ID: ' . implode(', ', $ids);
        $rows = (new SuratModel(Connection::get()))->exportRows($fromDate, $throughDate, $ids);
        $filename = 'surat-masuk-' . ($ids === [] ? $range : 'pilihan') . '-' . gmdate('Ymd');

        return [$rows, $rangeLabel, $filename];
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
    
    private function safeCsvCell(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '';
        
        return preg_match('/^[\x00-\x20]*[=+@\-]/u', $text) ? "'" . $text : $text;
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