<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use DomainException;
use RuntimeException;

final class SuratModel
{
    public const DISPOSISI_DEFAULT_TAHAP = [1, 2, 3];
    public const DISPOSISI_TAHAP = [1, 2, 3, 4, 5];
    public const PAGE_SIZE = 25;

    private const LIST_COLUMNS = 's.id, s.kode_pencarian, s.no, s.nomor_surat, s.tanggal_surat, s.tanggal_masuk,
        s.sifat, s.surat_dari, s.hal, s.link_drive, s.selesai_tahap, s.link_selesai,
        (SELECT username FROM users u WHERE u.id = s.selesai_oleh) AS selesai_oleh_nama,
        (SELECT d.asal FROM surat_disposisi d WHERE d.surat_id = s.id AND d.tahap = s.selesai_tahap) AS pejabat_akhir,
        LEFT(s.hal, 140) AS ringkas,
        (SELECT COUNT(*) FROM surat_disposisi d WHERE d.surat_id = s.id) AS jumlah_disposisi';

    private const SEARCH_COLUMNS = [
        's.kode_pencarian', 'CAST(s.id AS CHAR)', 'CAST(s.no AS CHAR)', 's.nomor_surat', 's.surat_dari', 's.hal',
    ];
    private const FULLTEXT_COLUMNS = 's.nomor_surat, s.surat_dari, s.pengusul, s.hal, s.catatan';

    public function __construct(private PDO $pdo)
    {
    }

    public function dashboardSummary(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) AS total,
                (SELECT COUNT(*) FROM surat_masuk WHERE selesai_tahap > 0) AS disposisi
             FROM surat_masuk'
        );
        $statement->execute();
        $summary = $statement->fetch();

        $latestStatement = $this->pdo->prepare(
            'SELECT ' . self::LIST_COLUMNS . ' FROM surat_masuk s ORDER BY s.id DESC LIMIT 5'
        );
        $latestStatement->execute();

        return [
            'total' => (int) $summary['total'],
            'disposisi' => (int) $summary['disposisi'],
            'terbaru' => $latestStatement->fetchAll(),
        ];
    }

    public function search(array $filters, int $page = 1): array
    {
        [$where, $parameters] = $this->searchConditions($filters);
        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM surat_masuk s' . $where);
        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $page), $pages);

        $statement = $this->pdo->prepare(
            'SELECT ' . self::LIST_COLUMNS . ' FROM surat_masuk s' . $where .
            ' ORDER BY s.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }
        $statement->bindValue(':limit', self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(),
            'page' => $page,
            'pages' => $pages,
            'perPage' => self::PAGE_SIZE,
            'total' => $total,
        ];
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM surat_masuk WHERE id = :id');
        $statement->execute(['id' => $id]);
        $surat = $statement->fetch();
        if (!$surat) {
            return null;
        }

        $surat['disposisi'] = $this->disposisi($id);

        return $surat;
    }

    public function disposisi(int $suratId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT tahap, asal, tujuan, tanggal, catatan, link_lampiran
             FROM surat_disposisi WHERE surat_id = :id ORDER BY tahap'
        );
        $statement->execute(['id' => $suratId]);
        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $result[(int) $row['tahap']] = $row;
        }

        return $result;
    }

    public function dispositionHistory(int $page = 1): array
    {
        $perPage = 25;
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM surat_masuk WHERE selesai_tahap > 0')->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $statement = $this->pdo->prepare(
            'SELECT s.id AS surat_id, s.no, s.selesai_tahap, d.asal AS pejabat_akhir,
                    d.tanggal AS tanggal_disposisi, s.selesai_pada AS dicatat_pada,
                    recorder.username AS dicatat_oleh, s.hal
             FROM surat_masuk s
             LEFT JOIN surat_disposisi d ON d.surat_id = s.id AND d.tahap = s.selesai_tahap
             LEFT JOIN users recorder ON recorder.id = s.selesai_oleh
             WHERE s.selesai_tahap > 0
             ORDER BY d.tanggal DESC, s.id DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(), 'page' => $page, 'pages' => $pages, 'total' => $total];
    }

    public function create(array $data, ?int $userId): int
    {
        $this->pdo->beginTransaction();
        try {
            $initializeSequence = $this->pdo->prepare(
                'INSERT IGNORE INTO surat_sequences (id, next_no) VALUES (1, 1)'
            );
            $initializeSequence->execute();
            $sequenceWasCreated = $initializeSequence->rowCount() === 1;

            $sequenceStatement = $this->pdo->prepare('SELECT next_no FROM surat_sequences WHERE id = 1 FOR UPDATE');
            $sequenceStatement->execute();
            $number = $sequenceStatement->fetchColumn();
            if ($number === false) {
                throw new RuntimeException('Surat number sequence is not initialized.');
            }
            $number = (int) $number;
            if ($sequenceWasCreated) {
                $minimumNumber = (int) $this->pdo->query(
                    'SELECT COALESCE(MAX(no), 0) + 1 FROM surat_masuk'
                )->fetchColumn();
                $number = max($number, $minimumNumber);
            }

            $this->pdo->prepare('UPDATE surat_sequences SET next_no = :next_no WHERE id = 1')
                ->execute(['next_no' => $number + 1]);

            $completedStage = (int) ($data['selesai_tahap'] ?? 0);
            $columns = array_merge($this->suratColumns($data), [
                'no' => $number,
                'created_by' => $userId,
                'selesai_oleh' => $completedStage > 0 ? $userId : null,
                'selesai_pada' => $completedStage > 0 ? gmdate('Y-m-d H:i:s') : null,
            ]);
            $names = array_keys($columns);
            $statement = $this->pdo->prepare(
                'INSERT INTO surat_masuk (' . implode(', ', $names) . ') VALUES (:' . implode(', :', $names) . ')'
            );
            $statement->execute($columns);
            $id = (int) $this->pdo->lastInsertId();

            $searchId = $this->pdo->prepare('UPDATE surat_masuk SET kode_pencarian = :kode WHERE id = :id');
            $searchId->execute([
                'kode' => 'BAPPERIDA-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT),
                'id' => $id,
            ]);

            $this->saveDisposisi($id, $data['disposisi']);
            (new AdminInboxModel($this->pdo))->createLetterNotification(
                $id,
                $userId ?? 0,
                str_pad((string) $number, 4, '0', STR_PAD_LEFT)
            );
            (new ArchiveSyncModel($this->pdo))->enqueueUpsert($id);
            $this->pdo->commit();

            return $id;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function update(int $id, array $data): void
    {
        $this->pdo->beginTransaction();
        try {
            $currentStatement = $this->pdo->prepare(
                'SELECT selesai_tahap, selesai_oleh, selesai_pada FROM surat_masuk WHERE id = :id FOR UPDATE'
            );
            $currentStatement->execute(['id' => $id]);
            $current = $currentStatement->fetch();
            if (!$current) {
                throw new RuntimeException('Surat tidak ditemukan.');
            }

            $userId = (int) ($_SESSION['user_id'] ?? 0);
            $editRequestId = null;
            if ((currentUser()['role'] ?? '') === 'pelayanan') {
                $editRequestId = (new AdminInboxModel($this->pdo))->lockApprovedEditRequest($id, $userId);
                if ($editRequestId === null) {
                    throw new DomainException('Persetujuan edit telah berakhir. Ajukan persetujuan baru untuk surat ini.');
                }
            }

            $completedStage = (int) ($data['selesai_tahap'] ?? 0);
            $changedCompletion = $completedStage !== (int) $current['selesai_tahap'];
            $columns = $this->suratColumns($data);
            $columns['selesai_oleh'] = $completedStage === 0
                ? null
                : ($changedCompletion || $current['selesai_oleh'] === null ? (int) ($_SESSION['user_id'] ?? 0) : $current['selesai_oleh']);
            $columns['selesai_pada'] = $completedStage === 0
                ? null
                : ($changedCompletion || $current['selesai_pada'] === null ? gmdate('Y-m-d H:i:s') : $current['selesai_pada']);
            $assignments = [];
            foreach (array_keys($columns) as $name) {
                $assignments[] = $name . ' = :' . $name;
            }
            $columns['id'] = $id;
            $statement = $this->pdo->prepare(
                'UPDATE surat_masuk SET ' . implode(', ', $assignments) . ' WHERE id = :id'
            );
            $statement->execute($columns);

            $this->saveDisposisi($id, $data['disposisi']);
            (new ArchiveSyncModel($this->pdo))->enqueueUpsert($id);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function delete(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            $lookup = $this->pdo->prepare(
                'SELECT kode_pencarian FROM surat_masuk WHERE id = :id FOR UPDATE'
            );
            $lookup->execute(['id' => $id]);
            $searchId = $lookup->fetchColumn();
            if ($searchId === false) {
                $this->pdo->commit();

                return false;
            }

            if (is_string($searchId) && $searchId !== '') {
                (new ArchiveSyncModel($this->pdo))->enqueueDelete($id);
            }
            $statement = $this->pdo->prepare('DELETE FROM surat_masuk WHERE id = :id');
            $statement->execute(['id' => $id]);
            $deleted = $statement->rowCount() > 0;
            $this->pdo->commit();

            return $deleted;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function deleteMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        if (count($ids) > 100) {
            throw new RuntimeException('Maksimal 100 surat dapat diproses sekaligus.');
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $this->pdo->beginTransaction();
        try {
            $lookup = $this->pdo->prepare(
                'SELECT id, kode_pencarian FROM surat_masuk WHERE id IN (' . $placeholders . ') FOR UPDATE'
            );
            $lookup->execute($ids);
            $records = $lookup->fetchAll();
            $deletedIds = array_map('intval', array_column($records, 'id'));
            foreach ($records as $record) {
                if (is_string($record['kode_pencarian']) && $record['kode_pencarian'] !== '') {
                    (new ArchiveSyncModel($this->pdo))->enqueueDelete((int) $record['id']);
                }
            }
            if ($deletedIds !== []) {
                $delete = $this->pdo->prepare(
                    'DELETE FROM surat_masuk WHERE id IN (' . implode(', ', array_fill(0, count($deletedIds), '?')) . ')'
                );
                $delete->execute($deletedIds);
            }
            $this->pdo->commit();

            return $deletedIds;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function findExportCandidate(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, no, tanggal_masuk, surat_dari, hal
             FROM surat_masuk WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $letter = $statement->fetch();

        return $letter ?: null;
    }

    public function exportRows(?string $fromDate = null, ?string $throughDate = null, array $ids = []): array
    {
        $conditions = [];
        $parameters = [];
        if ($ids !== []) {
            $placeholders = [];
            foreach (array_values(array_unique(array_map('intval', $ids))) as $index => $id) {
                $name = 'selected_id_' . $index;
                $placeholders[] = ':' . $name;
                $parameters[$name] = $id;
            }
            $conditions[] = 's.id IN (' . implode(', ', $placeholders) . ')';
        } elseif ($fromDate !== null && $throughDate !== null) {
            $conditions[] = 's.tanggal_masuk BETWEEN :from_date AND :through_date';
            $parameters['from_date'] = $fromDate;
            $parameters['through_date'] = $throughDate;
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $statement = $this->pdo->prepare(
            'SELECT s.id, s.kode_pencarian, s.no, s.tanggal_masuk, s.surat_dari, s.pengusul,
                    s.nomor_surat, s.tanggal_surat, s.sifat, s.hal, s.catatan, s.link_drive,
                    s.selesai_tahap, s.link_selesai, s.created_at, s.updated_at,
                    s.selesai_pada, finished.asal AS pejabat_akhir, finished.tanggal AS tanggal_akhir,
                    recorder.username AS dicatat_oleh, creator.username AS dibuat_oleh
             FROM surat_masuk s
             LEFT JOIN surat_disposisi finished ON finished.surat_id = s.id AND finished.tahap = s.selesai_tahap
             LEFT JOIN users recorder ON recorder.id = s.selesai_oleh
             LEFT JOIN users creator ON creator.id = s.created_by' . $where . '
             ORDER BY s.id'
        );
        $statement->execute($parameters);
        $letters = $statement->fetchAll();
        if ($letters === []) {
            return [];
        }

        $statement = $this->pdo->query(
            'SELECT surat_id, tahap, asal, tujuan, tanggal, catatan, link_lampiran
             FROM surat_disposisi ORDER BY surat_id, tahap'
        );
        $dispositions = [];
        foreach ($statement->fetchAll() as $row) {
            $dispositions[(int) $row['surat_id']][(int) $row['tahap']] = $row;
        }
        foreach ($letters as &$letter) {
            $letter['disposisi'] = $dispositions[(int) $letter['id']] ?? [];
        }
        unset($letter);

        return $letters;
    }

    private function searchConditions(array $filters): array
    {
        $conditions = [];
        $parameters = [];

        if ($filters['q'] !== '') {
            $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $filters['q']) ?? '';
            $tokens = preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $indexedTokens = array_values(array_filter($tokens, static fn (string $token): bool => mb_strlen($token) >= 3));
            $parts = [];

            if ($indexedTokens) {
                $parts[] = 'MATCH(' . self::FULLTEXT_COLUMNS . ') AGAINST (:fulltext IN BOOLEAN MODE)';
                $parameters['fulltext'] = implode(' ', array_map(static fn (string $token): string => '+' . $token . '*', $indexedTokens));
            }
            if (ctype_digit($filters['q'])) {
                $parts[] = 's.no = :agenda';
                $parameters['agenda'] = (int) $filters['q'];
                $parts[] = 's.id = :surat_id';
                $parameters['surat_id'] = (int) $filters['q'];
            } elseif (preg_match('/^#([0-9]+)$/', $filters['q'], $match) === 1) {
                $parts[] = 's.id = :surat_id';
                $parameters['surat_id'] = (int) $match[1];
            }
            if (preg_match('/^B-([0-9]+)$/i', $filters['q'], $match) === 1) {
                $parts[] = 's.no = :agenda_code';
                $parameters['agenda_code'] = (int) $match[1];
            }
            if (str_starts_with(strtoupper($filters['q']), 'BAPPERIDA-')) {
                $code = str_replace(['|', '%', '_'], ['||', '|%', '|_'], $filters['q']);
                $parts[] = "s.kode_pencarian LIKE :kode ESCAPE '|'";
                $parameters['kode'] = $code . '%';
            }

            if (!$parts) {
                $escapedQuery = '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $filters['q']) . '%';
                foreach (self::SEARCH_COLUMNS as $index => $column) {
                    $name = 'q' . $index;
                    $parts[] = $column . " LIKE :{$name} ESCAPE '|'";
                    $parameters[$name] = $escapedQuery;
                }
            }
            $conditions[] = '(' . implode(' OR ', $parts) . ')';
        }

        if ($filters['dari'] !== '') {
            $conditions[] = 's.tanggal_masuk >= :dari';
            $parameters['dari'] = $filters['dari'];
        }
        if ($filters['sampai'] !== '') {
            $conditions[] = 's.tanggal_masuk <= :sampai';
            $parameters['sampai'] = $filters['sampai'];
        }

        return [$conditions ? ' WHERE ' . implode(' AND ', $conditions) : '', $parameters];
    }

    private function suratColumns(array $data): array
    {
        $nullable = static fn (mixed $value): mixed => ($value === '' || $value === null) ? null : $value;

        return [
            'nomor_surat' => $nullable($data['nomor_surat']),
            'tanggal_surat' => $nullable($data['tanggal_surat']),
            'tanggal_masuk' => $data['tanggal_masuk'],
            'sifat' => $nullable($data['sifat']),
            'surat_dari' => $nullable($data['surat_dari']),
            'hal' => $nullable($data['hal']),
            'link_drive' => $nullable($data['link_drive']),
            'selesai_tahap' => (int) ($data['selesai_tahap'] ?? 0),
            'link_selesai' => $nullable($data['link_selesai'] ?? ''),
        ];
    }

    private function saveDisposisi(int $suratId, array $disposisi): void
    {
        $delete = $this->pdo->prepare('DELETE FROM surat_disposisi WHERE surat_id = :id AND tahap = :tahap');
        $upsert = $this->pdo->prepare(
            'INSERT INTO surat_disposisi (surat_id, tahap, asal, tujuan, tanggal, catatan, link_lampiran)
             VALUES (:id, :tahap, :asal, :tujuan, :tanggal, :catatan, NULL)
             ON DUPLICATE KEY UPDATE asal = VALUES(asal), tujuan = VALUES(tujuan), tanggal = VALUES(tanggal),
                 catatan = VALUES(catatan), link_lampiran = NULL'
        );

        foreach (self::DISPOSISI_TAHAP as $tahap) {
            $slot = $disposisi[$tahap] ?? null;
            if ($slot === null || ($slot['pejabat'] === '' && $slot['tujuan'] === [] && $slot['tanggal'] === '' && $slot['catatan'] === '')) {
                $delete->execute(['id' => $suratId, 'tahap' => $tahap]);
                continue;
            }

            $upsert->execute([
                'id' => $suratId,
                'tahap' => $tahap,
                'asal' => $slot['pejabat'] ?: null,
                'tujuan' => $slot['tujuan'] === [] ? null : implode(', ', $slot['tujuan']),
                'tanggal' => $slot['tanggal'] ?: null,
                'catatan' => $slot['catatan'] ?: null,
            ]);
        }
    }
}
