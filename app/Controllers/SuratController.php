<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Models\SuratModel;
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
        foreach ($result['items'] as &$item) {
            $item['driveUrl'] = $this->surat->safeDriveUrl($item['link_drive'] ?? null);
        }
        unset($item);
        render('surat/index', [
            'pageTitle' => 'Surat Masuk',
            'filters' => $filters,
            'data' => $result['items'],
            'pagination' => $result,
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::requireAdmin();
        $errors = [];
        $formData = $this->surat->emptyForm();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            requirePost();
            validateCsrf();
            [$formData, $errors] = $this->surat->validate($_POST);
            if (!$errors) {
                $this->surat->create($formData);
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

        $errors = [];
        $formData = $this->surat->formFromRecord($surat);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            requirePost();
            validateCsrf();
            [$formData, $errors] = $this->surat->validate($_POST);
            if (!$errors) {
                $this->surat->update($id, $formData);
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