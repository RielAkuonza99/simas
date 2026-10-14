<?php
declare(strict_types=1);

namespace App\Security;

final class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const MAX_IP_ATTEMPTS = 25;
    private const MAX_ACCOUNT_ATTEMPTS = 20;
    private const WINDOW_SECONDS = 900;

    public function isBlocked(string $identity): bool
    {
        return $this->withStore(static function (array &$attempts) use ($identity): bool {
            $key = hash('sha256', $identity);
            $now = time();
            $attempts[$key] = array_values(array_filter($attempts[$key] ?? [], static fn (int $time): bool => $time > $now - self::WINDOW_SECONDS));

            $limit = match (true) {
                str_starts_with($identity, 'account:') => self::MAX_ACCOUNT_ATTEMPTS,
                str_starts_with($identity, 'ip:') => self::MAX_IP_ATTEMPTS,
                default => self::MAX_ATTEMPTS,
            };

            return count($attempts[$key]) >= $limit;
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
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Login rate-limit storage is unavailable.');
        }

        $handle = @fopen($directory . '/login-attempts.json', 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Login rate-limit storage is unavailable.');
        }

        $locked = false;
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Login rate-limit storage cannot be locked.');
            }
            $locked = true;
            rewind($handle);
            $contents = stream_get_contents($handle);
            if ($contents === false) {
                throw new \RuntimeException('Login rate-limit storage cannot be read.');
            }
            try {
                $attempts = $contents === '' ? [] : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $error) {
                throw new \RuntimeException('Login rate-limit storage is invalid.', 0, $error);
            }
            if (!is_array($attempts)) {
                throw new \RuntimeException('Login rate-limit storage is invalid.');
            }

            $now = time();
            foreach ($attempts as $key => $times) {
                if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/', $key) || !is_array($times)) {
                    throw new \RuntimeException('Login rate-limit storage is invalid.');
                }
                foreach ($times as $time) {
                    if (!is_int($time)) {
                        throw new \RuntimeException('Login rate-limit storage is invalid.');
                    }
                }
                $attempts[$key] = array_values(array_filter($times, static fn (int $time): bool => $time > $now - self::WINDOW_SECONDS));
                if ($attempts[$key] === []) {
                    unset($attempts[$key]);
                }
            }

            $result = $callback($attempts);
            $contents = json_encode($attempts, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (!ftruncate($handle, 0) || !rewind($handle)) {
                throw new \RuntimeException('Login rate-limit storage cannot be updated.');
            }
            $written = 0;
            $length = strlen($contents);
            while ($written < $length) {
                $bytes = fwrite($handle, substr($contents, $written));
                if ($bytes === false || $bytes === 0) {
                    throw new \RuntimeException('Login rate-limit storage cannot be updated.');
                }
                $written += $bytes;
            }
            if (!fflush($handle)) {
                throw new \RuntimeException('Login rate-limit storage cannot be flushed.');
            }

            return $result;
        } finally {
            if ($locked) {
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    }
}