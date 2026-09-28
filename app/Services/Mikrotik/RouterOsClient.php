<?php

namespace App\Services\Mikrotik;

use RuntimeException;

class RouterOsClient implements HotspotRouter
{
    public function command(string $host, int $port, string $username, string $password, array $words, int $timeout = 5): array
    {
        $socket = @fsockopen($host, $port, $errno, $error, $timeout);

        if (! $socket) {
            throw new RuntimeException($error !== '' ? $error : 'Connexion impossible au routeur.');
        }

        stream_set_timeout($socket, $timeout);

        try {
            $this->writeSentence($socket, ['/login', '=name='.$username, '=password='.$password]);
            $login = $this->readSentences($socket);

            if ($this->hasTrap($login)) {
                throw new RuntimeException($this->trapMessage($login));
            }

            if ($this->hasChallenge($login)) {
                throw new RuntimeException('Ce routeur utilise un ancien mode de connexion. RouterOS 6.43 ou plus récent est requis.');
            }

            $this->writeSentence($socket, $words);

            return $this->readSentences($socket);
        } finally {
            fclose($socket);
        }
    }

    private function writeSentence($socket, array $words): void
    {
        fwrite($socket, RouterOsProtocol::encodeSentence($words));
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readSentences($socket): array
    {
        $rows = [];
        $current = [];

        while (! feof($socket)) {
            $word = $this->readWord($socket);

            if ($word === '') {
                if ($current === []) {
                    continue;
                }

                $rows[] = $current;
                $done = ($current['!type'] ?? '') === '!done' || ($current['!type'] ?? '') === '!fatal';
                $current = [];

                if ($done) {
                    break;
                }

                continue;
            }

            if (str_starts_with($word, '!')) {
                $current['!type'] = $word;

                continue;
            }

            if (str_starts_with($word, '=')) {
                $pair = substr($word, 1);
                $split = strpos($pair, '=');
                if ($split === false) {
                    $current[$pair] = '';
                } else {
                    $current[substr($pair, 0, $split)] = substr($pair, $split + 1);
                }
            }
        }

        if ($this->hasTrap($rows)) {
            throw new RuntimeException($this->trapMessage($rows));
        }

        return $rows;
    }

    private function readWord($socket): string
    {
        $first = fread($socket, 1);
        if ($first === false || $first === '') {
            return '';
        }

        $length = ord($first);

        if (($length & 0x80) === 0) {
            $size = $length;
        } elseif (($length & 0xC0) === 0x80) {
            $size = (($length & 0x3F) << 8) + ord((string) fread($socket, 1));
        } elseif (($length & 0xE0) === 0xC0) {
            $more = (string) fread($socket, 2);
            $size = (($length & 0x1F) << 16) + (ord($more[0]) << 8) + ord($more[1]);
        } elseif (($length & 0xF0) === 0xE0) {
            $more = (string) fread($socket, 3);
            $size = (($length & 0x0F) << 24) + (ord($more[0]) << 16) + (ord($more[1]) << 8) + ord($more[2]);
        } else {
            $more = (string) fread($socket, 4);
            $size = (ord($more[0]) << 24) + (ord($more[1]) << 16) + (ord($more[2]) << 8) + ord($more[3]);
        }

        if ($size === 0) {
            return '';
        }

        $data = '';
        while (strlen($data) < $size) {
            $chunk = fread($socket, $size - strlen($data));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
        }

        return $data;
    }

    private function hasTrap(array $rows): bool
    {
        foreach ($rows as $row) {
            if (($row['!type'] ?? '') === '!trap') {
                return true;
            }
        }

        return false;
    }

    private function hasChallenge(array $rows): bool
    {
        foreach ($rows as $row) {
            if (isset($row['ret']) && $row['ret'] !== '') {
                return true;
            }
        }

        return false;
    }

    private function trapMessage(array $rows): string
    {
        foreach ($rows as $row) {
            if (($row['!type'] ?? '') === '!trap') {
                return $row['message'] ?? 'Le routeur a refusé la commande.';
            }
        }

        return 'Le routeur a refusé la commande.';
    }
}
