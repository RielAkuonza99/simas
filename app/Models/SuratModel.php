<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

final class SuratModel
{
    public const DISPOSISI_DEFAULT_TAHAP = [1, 2, 3];
    public const DISPOSISI_TAHAP = [1, 2, 3, 4, 5];
    public const PAGE_SIZE = 25;

    private const LIST_COLUMNS = 's.id, s.kode_pencarian, s.no, s.nomor_surat, s.tanggal_surat, s.tanggal_masuk,
        s.sifat, s.surat_dari, s.pengusul, s.hal, s.catatan, s.link_drive, LEFT(s.hal, 140) AS ringkas,
        (SELECT COUNT(*) FROM surat_disposisi d WHERE d.surat_id = s.id) AS jumlah_disposisi';

    private const SEARCH_COLUMNS = [
        's.kode_pencarian', 'CAST(s.no AS CHAR)', 's.nomor_surat', 's.surat_dari',
        's.pengusul', 's.hal', 's.catatan',
    ];
    private const FULLTEXT_COLUMNS = 's.nomor_surat, s.surat_dari, s.pengusul, s.hal, s.catatan';

    public function __construct(private PDO $pdo)
    {
    }

    public function dashboardSummary(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) AS total,
                (SELECT COUNT(DISTINCT surat_id) FROM surat_disposisi) AS disposisi
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

    public function create(array $data, ?int $userId): int
    {
        $this->pdo->beginTransaction();
        try {
            $sequenceStatement = $this->pdo->prepare('SELECT next_no FROM surat_sequences WHERE id = 1 FOR UPDATE');
            $sequenceStatement->execute();
            $number = $sequenceStatement->fetchColumn();
            if ($number === false) {
                throw new RuntimeException('Surat number sequence is not initialized.');
            }

            $this->pdo->prepare('UPDATE surat_sequences SET next_no = next_no + 1 WHERE id = 1')->execute();

            $columns = array_merge($this->suratColumns($data), [
                'no' => (int) $number,
                'created_by' => $userId,
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
            $columns = $this->suratColumns($data);
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
            'pengusul' => $nullable($data['pengusul']),
            'hal' => $nullable($data['hal']),
            'catatan' => $nullable($data['catatan']),
            'link_drive' => $nullable($data['link_drive']),
        ];
    }

    private function saveDisposisi(int $suratId, array $disposisi): void
    {
        $delete = $this->pdo->prepare('DELETE FROM surat_disposisi WHERE surat_id = :id AND tahap = :tahap');
        $upsert = $this->pdo->prepare(
            'INSERT INTO surat_disposisi (surat_id, tahap, asal, tujuan, tanggal, catatan, link_lampiran)
             VALUES (:id, :tahap, :asal, :tujuan, :tanggal, :catatan, :link_lampiran)
             ON DUPLICATE KEY UPDATE asal = VALUES(asal), tujuan = VALUES(tujuan), tanggal = VALUES(tanggal),
                 catatan = VALUES(catatan), link_lampiran = VALUES(link_lampiran)'
        );

        foreach (self::DISPOSISI_TAHAP as $tahap) {
            $slot = $disposisi[$tahap] ?? null;
            if ($slot === null || ($slot['asal'] === '' && $slot['tujuan'] === '' && $slot['tanggal'] === '' && $slot['catatan'] === '' && $slot['link_lampiran'] === '')) {
                $delete->execute(['id' => $suratId, 'tahap' => $tahap]);
                continue;
            }

            $upsert->execute([
                'id' => $suratId,
                'tahap' => $tahap,
                'asal' => $slot['asal'] ?: null,
                'tujuan' => $slot['tujuan'] ?: null,
                'tanggal' => $slot['tanggal'] ?: null,
                'catatan' => $slot['catatan'] ?: null,
                'link_lampiran' => $slot['link_lampiran'] ?: null,
            ]);
        }
    }
}
