<?php
declare(strict_types=1);

namespace App\Security;

final class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 900;

    public function isBlocked(string $identity): bool
    {
        return $this->withStore(static function (array &$attempts) use ($identity): bool {
            $key = hash('sha256', $identity);
            $now = time();
            $attempts[$key] = array_values(array_filter($attempts[$key] ?? [], static fn (int $time): bool => $time > $now - self::WINDOW_SECONDS));

            return count($attempts[$key]) >= self::MAX_ATTEMPTS;
        });
    }

    public function recordFailure(string $identity): void
    {
        $this->withStore(static function (array &$attempts) use ($identity): void {
            $key = hash('sha256', $identity);
            $now = time();
            $attempts[$key] = array_values(array_filter($attempts[$key] ?? [], static fn (int $time): bool => $time > $now - self::WINDOW_SECONDS));
            $attempts[$key][] = $now;
        });
    }

    public function clear(string $identity): void
    {
        $this->withStore(static function (array &$attempts) use ($identity): void {
            unset($attempts[hash('sha256', $identity)]);
        });
    }

    private function withStore(callable $callback): mixed
    {
        $directory = APP_ROOT . '/storage/private';
        if (!is_dir($directory)) {
            mkdir($directory, 0750, true);
        }

        $handle = fopen($directory . '/login-attempts.json', 'c+');
        if ($handle === false) {
            return false;
        }

        try {
            flock($handle, LOCK_EX);
            $contents = stream_get_contents($handle);
            $attempts = json_decode($contents ?: '{}', true);
            if (!is_array($attempts)) {
                $attempts = [];
            }

            $result = $callback($attempts);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($attempts, JSON_UNESCAPED_SLASHES));

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}