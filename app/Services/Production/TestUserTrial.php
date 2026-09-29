<?php

namespace App\Services\Production;

use App\Models\Mikrotik;
use App\Services\AuditLogger;
use App\Services\Mikrotik\MikrotikService;
use App\Support\Correlation;
use Illuminate\Support\Str;
use RuntimeException;

class TestUserTrial
{
    public function __construct(
        private RouterExamination $examination,
        private MikrotikService $mikrotik,
        private AuditLogger $audit,
        private Correlation $correlation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function create(Mikrotik $router, string $profile): array
    {
        $probe = $this->examination->run($router);
        $username = 'LIMETE_TEST_'.now()->format('YmdHis');
        $base = [
            'username' => $username,
            'correlation_id' => $probe['correlation_id'],
            'created' => false,
            'confirmed' => false,
            'status' => 'NOT TESTED',
        ];

        if (! $probe['authenticated']) {
            return array_merge($base, [
                'status' => $probe['documentation'] ? 'NOT TESTED' : 'FAIL',
                'detail' => $probe['error'] ?: 'REAL MIKROTIK NOT TESTED',
            ]);
        }

        $known = $probe['profile_names'] ?? [];
        if (! in_array($profile, $known, true)) {
            return array_merge($base, [
                'status' => 'FAIL',
                'detail' => 'FAIL — profil inexistant sur le MikroTik. Le compte n’a pas été créé.',
            ]);
        }

        $password = Str::lower(Str::random(12));

        try {
            $this->mikrotik->guardedCommand($router, [
                '/ip/hotspot/user/add',
                '=name='.$username,
                '=password='.$password,
                '=profile='.$profile,
                '=limit-uptime=5m',
                '=comment=limete-test',
            ], [$password]);
            $found = $this->mikrotik->readPrint($router, '/ip/hotspot/user/print', ['?name='.$username]);
        } catch (RuntimeException $exception) {
            return array_merge($base, [
                'status' => 'FAIL',
                'detail' => $this->redact($exception->getMessage(), $router, $password),
            ]);
        }

        $match = collect($found)->first(fn (array $row) => ($row['name'] ?? '') === $username);
        if ($match === null) {
            return array_merge($base, [
                'status' => 'FAIL',
                'created' => false,
                'confirmed' => false,
                'detail' => 'RouterOS n’a pas confirmé '.$username.'. La création n’est pas considérée comme réussie.',
            ]);
        }

        $details = $router->details ?? [];
        $details['last_test_user'] = $username;
        $router->forceFill(['details' => $details])->save();
        $this->audit->record('mikrotik.test_user_created', $router, null, [
            'username' => $username,
            'profile' => $profile,
            'correlation_id' => $this->correlation->id(),
            'confirmed' => true,
        ]);

        return array_merge($base, [
            'status' => 'PASS',
            'created' => true,
            'confirmed' => true,
            'detail' => $username.' lu sur le routeur. Le mot de passe n’est pas affiché.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(Mikrotik $router, string $username): array
    {
        $correlationId = $this->correlation->reset();
        if (! str_starts_with($username, 'LIMETE_TEST_')) {
            return [
                'status' => 'FAIL',
                'deleted' => false,
                'correlation_id' => $correlationId,
                'detail' => 'Seuls les utilisateurs LIMETE_TEST_ créés par le système peuvent être supprimés.',
            ];
        }

        if ($this->examination->isDocumentationHost((string) $router->host)) {
            return [
                'status' => 'NOT TESTED',
                'deleted' => false,
                'correlation_id' => $correlationId,
                'detail' => 'REAL MIKROTIK NOT TESTED',
            ];
        }

        try {
            $found = $this->mikrotik->readPrint($router, '/ip/hotspot/user/print', ['?name='.$username]);
        } catch (RuntimeException $exception) {
            return [
                'status' => 'FAIL',
                'deleted' => false,
                'correlation_id' => $correlationId,
                'detail' => $this->redact($exception->getMessage(), $router, ''),
            ];
        }

        $match = collect($found)->first(fn (array $row) => ($row['name'] ?? '') === $username);
        $comment = (string) ($match['comment'] ?? '');
        $stored = (string) ($router->detail('last_test_user') ?? '');
        $owned = $match !== null && ($comment === 'limete-test' || $username === $stored);
        if (! $owned) {
            return [
                'status' => 'FAIL',
                'deleted' => false,
                'correlation_id' => $correlationId,
                'detail' => 'Cet utilisateur n’est pas un compte de test créé par le système. Aucune suppression.',
            ];
        }

        $id = $match['.id'] ?? null;
        if (! is_string($id) || $id === '') {
            return [
                'status' => 'FAIL',
                'deleted' => false,
                'correlation_id' => $correlationId,
                'detail' => 'Identifiant RouterOS non lu. Aucune suppression.',
            ];
        }

        try {
            $this->mikrotik->guardedCommand($router, ['/ip/hotspot/user/remove', '=.id='.$id], [], ['username' => $username]);
            $again = $this->mikrotik->readPrint($router, '/ip/hotspot/user/print', ['?name='.$username]);
        } catch (RuntimeException $exception) {
            return [
                'status' => 'FAIL',
                'deleted' => false,
                'correlation_id' => $correlationId,
                'detail' => $this->redact($exception->getMessage(), $router, ''),
            ];
        }

        $still = collect($again)->first(fn (array $row) => ($row['name'] ?? '') === $username);
        if ($still !== null) {
            return [
                'status' => 'FAIL',
                'deleted' => false,
                'correlation_id' => $correlationId,
                'detail' => $username.' est encore présent sur le routeur.',
            ];
        }

        $details = $router->details ?? [];
        if (($details['last_test_user'] ?? null) === $username) {
            unset($details['last_test_user']);
            $router->forceFill(['details' => $details])->save();
        }
        $this->audit->record('mikrotik.test_user_deleted', $router, null, [
            'username' => $username,
            'correlation_id' => $correlationId,
        ]);

        return [
            'status' => 'PASS',
            'deleted' => true,
            'correlation_id' => $correlationId,
            'detail' => $username.' supprimé. La lecture suivante ne le retrouve pas.',
        ];
    }

    private function redact(string $message, Mikrotik $router, string $extra): string
    {
        foreach ([(string) $router->password, $extra] as $secret) {
            if ($secret !== '') {
                $message = str_replace($secret, '[masqué]', $message);
            }
        }

        return (string) preg_replace('/=password=[^\s,]*/', '=password=[masqué]', $message);
    }
}
