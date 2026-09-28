<?php

namespace App\Services\Mikrotik;

class HostResolver
{
    /**
     * @return array{checked: bool, addresses: array<int, string>, label: string}
     */
    public function resolve(string $host): array
    {
        $unknown = [
            'checked' => false,
            'addresses' => [],
            'label' => 'Résolution non vérifiée',
        ];
        if (! preg_match('/^[a-z0-9][a-z0-9.-]{0,158}[a-z0-9]$/i', $host)) {
            return $unknown;
        }

        $records = @dns_get_record($host, DNS_A);
        if ($records === false) {
            return $unknown;
        }

        $addresses = [];
        foreach ($records as $record) {
            if (! empty($record['ip']) && is_string($record['ip'])) {
                $addresses[] = $record['ip'];
            }
        }

        if ($addresses === []) {
            return [
                'checked' => true,
                'addresses' => [],
                'label' => 'Aucune adresse trouvée pour ce nom. Le portail n’est pas vérifié.',
            ];
        }

        return [
            'checked' => true,
            'addresses' => $addresses,
            'label' => 'Le nom se résout. Cela ne garantit pas que le portail répond.',
        ];
    }
}
