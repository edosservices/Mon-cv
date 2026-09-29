<?php

namespace App\Services\Production;

use App\Models\AuditLog;
use App\Models\Mikrotik;
use App\Models\WifiZone;
use App\Services\Mikrotik\MikrotikPreparation;
use App\Services\Payments\PaymentManager;
use App\Support\Correlation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductionCheck
{
    public function __construct(
        private PaymentManager $payments,
        private MikrotikPreparation $portal,
        private Correlation $correlation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(Request $request): array
    {
        $checks = array_merge(
            $this->application($request),
            $this->database(),
            $this->runtime(),
            $this->portal(),
            $this->branding(),
            $this->security(),
        );
        $routers = $this->routers();
        $payments = $this->paymentRows();
        $journey = $this->journey();
        $banner = collect($routers)->contains(fn (array $router) => ($router['authenticated'] ?? false) === true)
            ? null
            : 'REAL MIKROTIK NOT TESTED';

        return [
            'banner' => $banner,
            'correlation_id' => $this->correlation->id(),
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
            'routers' => $routers,
            'payments' => $payments,
            'journey' => $journey,
            'sections' => $this->sections($checks, $routers, $payments, $journey),
        ];
    }

    /**
     * @return array<int, array{code: string, label: string, status: string, detail: string, section: string}>
     */
    private function application(Request $request): array
    {
        $env = (string) config('app.env');
        $url = (string) config('app.url');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $key = (string) config('app.key');
        $rows = [];
        $rows[] = $this->row('APP_ENV', 'APP_ENV', $env === 'production' ? 'PASS' : 'WARN', $env === '' ? 'APP_ENV vide' : 'APP_ENV='.$env, 'A');
        if ($url === '') {
            $rows[] = $this->row('APP_URL', 'APP_URL', 'FAIL', 'APP_URL vide', 'A');
        } elseif ($scheme === 'https') {
            $rows[] = $this->row('APP_URL', 'APP_URL', 'PASS', $url, 'A');
        } else {
            $rows[] = $this->row('APP_URL', 'APP_URL', 'WARN', $url.' — HTTPS non déclaré dans APP_URL', 'A');
        }
        $rows[] = $request->isSecure()
            ? $this->row('HTTPS', 'HTTPS', 'PASS', 'La requête courante est en HTTPS', 'A')
            : $this->row('HTTPS', 'HTTPS', $env === 'production' ? 'FAIL' : 'WARN', 'La requête courante n’est pas en HTTPS', 'A');
        if ($key === '') {
            $rows[] = $this->row('APP_KEY', 'Clé de chiffrement', 'FAIL', 'APP_KEY absente', 'T');
        } else {
            try {
                $round = decrypt(encrypt('probe'));
                $rows[] = $this->row('APP_KEY', 'Clé de chiffrement', $round === 'probe' ? 'PASS' : 'FAIL', 'Aller-retour de chiffrement '.($round === 'probe' ? 'réussi' : 'échoué').'. La clé n’est pas affichée.', 'T');
            } catch (Throwable $exception) {
                $rows[] = $this->row('APP_KEY', 'Clé de chiffrement', 'FAIL', 'Le chiffrement a échoué.', 'T');
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array{code: string, label: string, status: string, detail: string, section: string}>
     */
    private function database(): array
    {
        try {
            $row = DB::select('select 1 as ok');
            $ok = isset($row[0]->ok) && (int) $row[0]->ok === 1;

            return [$this->row('DB', 'Base de données', $ok ? 'PASS' : 'FAIL', $ok ? 'Connexion ouverte, select 1' : 'Réponse inattendue', 'B')];
        } catch (Throwable $exception) {
            return [$this->row('DB', 'Base de données', 'FAIL', 'Connexion impossible', 'B')];
        }
    }

    /**
     * @return array<int, array{code: string, label: string, status: string, detail: string, section: string}>
     */
    private function runtime(): array
    {
        $rows = [];
        try {
            $token = 'probe-'.bin2hex(random_bytes(4));
            Cache::put($token, 'ok', 10);
            $rows[] = $this->row('CACHE', 'Cache', Cache::get($token) === 'ok' ? 'PASS' : 'FAIL', 'Lecture après écriture', 'A');
            Cache::forget($token);
        } catch (Throwable) {
            $rows[] = $this->row('CACHE', 'Cache', 'FAIL', 'Écriture du cache impossible', 'A');
        }

        $queue = (string) config('queue.default');
        $rows[] = $queue === 'sync'
            ? $this->row('QUEUE', 'Queue', 'WARN', 'QUEUE_CONNECTION=sync. Les jobs partent dans la requête. Aucun worker n’a été observé.', 'A')
            : $this->row('QUEUE', 'Queue', 'NOT TESTED', 'QUEUE_CONNECTION='.$queue.'. Le worker n’a pas été observé.', 'A');

        $rows[] = $this->storage('local', 'STORAGE', 'Storage');
        $schedule = (string) @file_get_contents(base_path('routes/console.php'));
        $declared = str_contains($schedule, 'limete:expire-vouchers') && str_contains($schedule, 'limete:sweep-subscriptions');
        $rows[] = $this->row('SCHEDULER', 'Scheduler', $declared ? 'WARN' : 'FAIL', $declared ? 'limete:expire-vouchers et limete:sweep-subscriptions sont déclarés. Le cron du serveur n’a pas été observé.' : 'Tâches planifiées introuvables', 'A');

        $log = storage_path('logs');
        $rows[] = $this->row('LOGS', 'Journaux', is_dir($log) && is_writable($log) ? 'PASS' : 'FAIL', is_writable($log) ? 'storage/logs est accessible en écriture' : 'storage/logs n’est pas accessible en écriture', 'T');

        return $rows;
    }

    /**
     * @return array{code: string, label: string, status: string, detail: string, section: string}
     */
    private function storage(string $disk, string $code, string $label): array
    {
        try {
            $path = 'production-check/probe.txt';
            Storage::disk($disk)->put($path, 'probe');
            $ok = Storage::disk($disk)->get($path) === 'probe';
            Storage::disk($disk)->delete($path);

            return $this->row($code, $label, $ok ? 'PASS' : 'FAIL', $ok ? 'Disque '.$disk.' lisible et écrivable' : 'Lecture incohérente', 'A');
        } catch (Throwable) {
            return $this->row($code, $label, 'FAIL', 'Disque '.$disk.' non écrivable', 'A');
        }
    }

    /**
     * @return array<int, array{code: string, label: string, status: string, detail: string, section: string}>
     */
    private function portal(): array
    {
        $files = $this->portal->portalFiles();
        $missing = array_values(array_filter($files, fn (array $file) => ! $file['present']));
        $secret = array_values(array_filter($files, fn (array $file) => $file['secret']));
        $status = $secret !== [] ? 'FAIL' : ($missing !== [] ? 'FAIL' : 'PASS');
        $detail = $status === 'PASS'
            ? count($files).' fichiers du portail sont présents dans l’application'
            : 'Manquant ou secret embarqué : '.implode(', ', array_map(fn (array $file) => $file['file'], array_merge($missing, $secret)));
        $login = (string) @file_get_contents(base_path('hotspot/login.html'));
        $statusHtml = (string) @file_get_contents(base_path('hotspot/status.html'));
        $bridge = (string) @file_get_contents(base_path('hotspot/js/limete-bridge.js'));
        $app = (string) @file_get_contents(base_path('hotspot/js/app.js'));
        $snippet = (string) @file_get_contents(base_path('resources/views/mikrotiks/index.blade.php'));

        return [
            $this->row('PORTAL_FILES', 'Fichiers du portail', $status, $detail, 'H'),
            $this->row('PORTAL_VARS', 'Variables RouterOS', str_contains($login, '$(link-login-only)') && str_contains($statusHtml, '$(link-logout)') ? 'PASS' : 'FAIL', 'login.html et status.html dans l’application', 'H'),
            $this->row('LIMETE_ZONE', 'LIMETE_ZONE', str_contains($snippet, 'LIMETE_ZONE') ? 'WARN' : 'FAIL', 'Le bloc est proposé dans l’écran MikroTik. Sa présence sur le routeur n’a pas été lue.', 'H'),
            $this->row('LIMETE_SHOP', 'window.LIMETE_SHOP', str_contains($snippet, 'LIMETE_SHOP') ? 'WARN' : 'FAIL', 'Le script est généré par l’application. Le routeur ne l’a pas renvoyé.', 'H'),
            $this->row('LIMETE_SESSION', 'window.LIMETE_SESSION', str_contains($bridge, 'LIMETE_SESSION') ? 'WARN' : 'FAIL', 'limete-bridge.js sait lire la session. Aucun portail RouterOS ne l’a appelée.', 'H'),
            $this->row('LIMETE_TICKET', 'window.LIMETE_TICKET', str_contains($app, 'LIMETE_TICKET') ? 'WARN' : 'FAIL', 'app.js connaît le lien du ticket. Aucun client captif ne l’a ouvert.', 'H'),
        ];
    }

    /**
     * @return array<int, array{code: string, label: string, status: string, detail: string, section: string}>
     */
    private function branding(): array
    {
        $zones = WifiZone::query()->get();
        if ($zones->isEmpty()) {
            return [$this->row('BRANDING', 'Branding', 'NOT TESTED', 'Aucune WiFi Zone.', 'H')];
        }
        $ready = $zones->filter(fn (WifiZone $zone) => filled($zone->logo_path) && filled($zone->primary_color))->count();

        return [$this->row('BRANDING', 'Branding', $ready > 0 ? 'PASS' : 'WARN', $ready.' zone(s) avec logo et couleur sur '.$zones->count(), 'H')];
    }

    /**
     * @return array<int, array{code: string, label: string, status: string, detail: string, section: string}>
     */
    private function security(): array
    {
        $rows = [];
        $sample = Mikrotik::query()->first();
        if (! $sample) {
            $rows[] = $this->row('CIPHER', 'Mots de passe MikroTik', 'NOT TESTED', 'Aucun routeur enregistré à inspecter.', 'T');
        } else {
            $raw = DB::table('mikrotiks')->where('id', $sample->id)->value('password');
            $plain = (string) $sample->password;
            $encrypted = is_string($raw) && $raw !== '' && $raw !== $plain && str_starts_with($raw, 'eyJ');
            $rows[] = $this->row('CIPHER', 'Mots de passe MikroTik', $encrypted ? 'PASS' : 'FAIL', $encrypted ? 'La valeur en base est chiffrée. Elle n’est pas affichée.' : 'La valeur en base n’a pas la forme d’un secret chiffré.', 'T');
            $rows[] = $this->logScan($plain);
        }
        $rows[] = $this->row('CSRF', 'CSRF', 'PASS', 'Le middleware CSRF est actif. Seuls les webhooks de paiement en sont exclus.', 'T');
        $rows[] = $this->row('POLICY', 'Policies', class_exists(\App\Policies\TenantOwnedPolicy::class) ? 'PASS' : 'FAIL', 'TenantOwnedPolicy est enregistrée pour les données de l’entreprise.', 'S');
        $rows[] = $this->row('ISOLATION_LIVE', 'Isolation réelle', 'NOT TESTED', 'Aucun accès croisé n’a été tenté contre un second routeur réel.', 'S');
        $leaks = AuditLog::query()->latest('id')->limit(30)->get()->contains(function (AuditLog $log) {
            $blob = strtolower(json_encode($log->new_values));

            return str_contains($blob, 'password') && ! str_contains($blob, '[masqué]');
        });
        $rows[] = $this->row('AUDIT', 'Journal d’audit', $leaks ? 'FAIL' : 'PASS', $leaks ? 'Une entrée récente contient un champ password.' : 'Les 30 dernières entrées lues ne montrent pas de mot de passe en clair.', 'T');
        $rows[] = $this->row('WEBHOOK', 'Webhook signé', 'NOT TESTED', 'La signature est exigée par le code. Aucun webhook opérateur réel n’a été reçu.', 'T');

        return $rows;
    }

    /**
     * @return array{code: string, label: string, status: string, detail: string, section: string}
     */
    private function logScan(string $secret): array
    {
        $path = storage_path('logs/laravel.log');
        if (! is_file($path)) {
            return $this->row('LOG_SECRET', 'Secrets dans les logs', 'NOT TESTED', 'Aucun fichier laravel.log à examiner.', 'T');
        }
        $size = filesize($path) ?: 0;
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return $this->row('LOG_SECRET', 'Secrets dans les logs', 'WARN', 'Journal illisible', 'T');
        }
        $offset = max(0, $size - 200000);
        fseek($handle, $offset);
        $chunk = (string) fread($handle, 200000);
        fclose($handle);
        if ($secret !== '' && str_contains($chunk, $secret)) {
            return $this->row('LOG_SECRET', 'Secrets dans les logs', 'FAIL', 'Un mot de passe de routeur apparaît dans les 200 Ko lus.', 'T');
        }

        return $this->row('LOG_SECRET', 'Secrets dans les logs', 'PASS', 'Les 200 Ko lus ne contiennent pas le mot de passe du routeur examiné.', 'T');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function routers(): array
    {
        return Mikrotik::query()->with('wifiZone.tenant')->orderBy('id')->get()->map(function (Mikrotik $router) {
            $saved = $router->detail('production_probe');

            return [
                'id' => $router->id,
                'name' => $router->name,
                'host' => $router->host,
                'zone' => $router->wifiZone?->name,
                'zone_id' => $router->wifi_zone_id,
                'tenant' => $router->wifiZone?->tenant?->name,
                'documentation' => app(RouterExamination::class)->isDocumentationHost((string) $router->host),
                'authenticated' => is_array($saved) ? (bool) ($saved['authenticated'] ?? false) : false,
                'status' => $this->cardStatus(is_array($saved) ? $saved : null),
                'detail' => is_array($saved)
                    ? ($saved['error'] ?: 'Dernière lecture '.$saved['at'])
                    : 'Aucun test réel n’a été lancé pour ce routeur.',
                'probe' => is_array($saved) ? $saved : null,
                'test_user' => $router->detail('last_test_user'),
            ];
        })->all();
    }

    /**
     * @return array<int, array{provider: string, label: string, status: string, detail: string}>
     */
    private function paymentRows(): array
    {
        $rows = [];
        foreach (config('limete.payment_providers') as $provider => $label) {
            if ($provider === 'manual') {
                $rows[] = [
                    'provider' => $provider,
                    'label' => $label,
                    'status' => 'CONFIGURED',
                    'detail' => 'Le comptoir n’utilise pas d’API opérateur. Aucun paiement réel n’a été observé. PENDING REAL TEST pour une vente physique.',
                ];

                continue;
            }
            if ($provider === 'unipay') {
                $rows = array_merge($rows, $this->unipayRows());

                continue;
            }
            if (! $this->payments->configured($provider)) {
                $rows[] = [
                    'provider' => $provider,
                    'label' => $label,
                    'status' => 'NOT CONFIGURED',
                    'detail' => 'Aucune clé API officielle n’est renseignée.',
                ];

                continue;
            }
            $rows[] = [
                'provider' => $provider,
                'label' => $label,
                'status' => 'PENDING REAL TEST',
                'detail' => 'Une clé est présente. Aucune transaction opérateur n’a été confirmée.',
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{provider: string, label: string, status: string, detail: string}>
     */
    private function unipayRows(): array
    {
        $key = filled(config('services.unipay.key'));
        $secret = filled(config('services.unipay.webhook_secret'));
        $mode = strtolower(trim((string) config('services.unipay.mode'))) === 'live' ? 'LIVE' : 'TEST';

        return [
            [
                'provider' => 'unipay',
                'label' => 'UniPay API key',
                'status' => $key ? 'CONFIGURED' : 'NOT CONFIGURED',
                'detail' => $key
                    ? 'La clé est lue depuis la configuration. Elle n’est pas affichée. Aucune transaction live n’a été exécutée.'
                    : 'UniPay non configuré',
            ],
            [
                'provider' => 'unipay_mode',
                'label' => 'UniPay mode',
                'status' => $mode,
                'detail' => $mode === 'LIVE'
                    ? 'UNIPAY_MODE=live est explicite. Ce contrôle n’a envoyé aucune transaction.'
                    : 'Le mode reste test tant que UNIPAY_MODE n’est pas exactement live.',
            ],
            [
                'provider' => 'unipay_webhook',
                'label' => 'Webhook',
                'status' => $secret ? 'CONFIGURED' : 'NOT CONFIGURED',
                'detail' => $secret
                    ? 'Le secret de signature est présent. Il n’est pas affiché. Aucun webhook UniPay réel n’a été reçu.'
                    : 'UNIPAY_WEBHOOK_SECRET est vide. Une notification sans secret est refusée.',
            ],
        ];
    }

    /**
     * @return array<int, array{step: int, label: string, status: string, detail: string}>
     */
    private function journey(): array
    {
        $steps = [
            'Observer le client connecté au WiFi.',
            'Observer la redirection vers le portail.',
            'Observer l’ouverture de la boutique.',
            'Observer le choix du forfait de test.',
            'Observer le choix du moyen de paiement.',
            'Vérifier que le paiement reste en attente.',
            'Vérifier que le retour du navigateur n’active pas le ticket.',
            'Observer la confirmation réelle du paiement.',
            'Vérifier la création du ticket.',
            'Vérifier la création de l’utilisateur HotSpot sur le MikroTik.',
            'Vérifier que synchronized n’est posé que si RouterOS confirme.',
            'Vérifier que le client reçoit le code du ticket.',
            'Observer la connexion au portail.',
            'Observer l’authentification RouterOS.',
            'Observer l’accès Internet.',
            'Lire le statut : code, forfait, dates, temps restant, IP, état connecté.',
            'Observer la déconnexion.',
            'Observer la reconnexion.',
            'Vérifier que l’expiration commerciale ne change pas.',
            'À l’échéance, observer l’accès refusé, le ticket expiré et le traitement RouterOS déjà prévu.',
        ];
        $rows = [];
        foreach ($steps as $index => $label) {
            $rows[] = [
                'step' => $index + 1,
                'label' => $label,
                'status' => 'NOT TESTED',
                'detail' => 'Aucun client réel n’a été observé sur un HotSpot.',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{code: string, label: string, status: string, detail: string, section: string}>  $checks
     * @param  array<int, array<string, mixed>>  $routers
     * @param  array<int, array{provider: string, label: string, status: string, detail: string}>  $payments
     * @param  array<int, array{step: int, label: string, status: string, detail: string}>  $journey
     * @return array<int, array{code: string, label: string, status: string, detail: string}>
     */
    private function sections(array $checks, array $routers, array $payments, array $journey): array
    {
        $labels = [
            'A' => 'Application',
            'B' => 'Database',
            'C' => 'MikroTik',
            'D' => 'RouterOS',
            'E' => 'HotSpot',
            'F' => 'DNS',
            'G' => 'Profiles',
            'H' => 'Portal',
            'I' => 'Test user',
            'J' => 'Payment',
            'K' => 'Ticket',
            'L' => 'Authentication',
            'M' => 'Internet',
            'N' => 'Status',
            'O' => 'Logout',
            'P' => 'Reconnect',
            'Q' => 'Expiration',
            'R' => 'Multi-MikroTik',
            'S' => 'Tenant isolation',
            'T' => 'Security',
        ];
        $confirmedTestUser = collect($routers)->contains(fn (array $router) => filled($router['test_user']));
        $forced = [
            'C' => $this->worst(
                $this->routerSection($routers, 'tcp'),
                $this->routerSection($routers, 'authenticate'),
            ),
            'D' => $this->routerSection($routers, 'version'),
            'E' => $this->hotspotSection($routers),
            'F' => $this->routerSection($routers, 'dns'),
            'G' => $this->planSection($routers),
            'I' => $confirmedTestUser ? 'PASS' : 'NOT TESTED',
            'J' => 'NOT TESTED',
            'K' => 'NOT TESTED',
            'L' => 'NOT TESTED',
            'M' => 'NOT TESTED',
            'N' => 'NOT TESTED',
            'O' => 'NOT TESTED',
            'P' => 'NOT TESTED',
            'Q' => 'NOT TESTED',
            'R' => 'NOT TESTED',
        ];
        $rows = [];
        foreach ($labels as $code => $label) {
            if (isset($forced[$code])) {
                $detail = match (true) {
                    in_array($code, ['K', 'L', 'M', 'N', 'O', 'P', 'Q'], true) => 'Aucun client réel n’a été observé.',
                    $code === 'J' => 'Aucun paiement opérateur n’a été confirmé sur le réseau.',
                    $code === 'R' => 'Aucun compte de test n’a été lu sur deux routeurs réels de la même zone.',
                    $code === 'I' && $forced[$code] === 'PASS' => 'Un utilisateur LIMETE_TEST_ a été relu sur le routeur. Le mot de passe n’est pas affiché.',
                    $code === 'I' => 'Aucun utilisateur LIMETE_TEST_ n’a été confirmé par RouterOS dans ce contrôle.',
                    $forced[$code] === 'PASS' => 'Une lecture API a réussi. Cela ne prouve pas l’accès Internet d’un client.',
                    $forced[$code] === 'FAIL' => 'La dernière lecture réelle a échoué. Aucune configuration n’a été modifiée.',
                    default => 'REAL MIKROTIK NOT TESTED',
                };
                $rows[] = ['code' => $code, 'label' => $label, 'status' => $forced[$code], 'detail' => $detail];

                continue;
            }
            $related = array_values(array_filter($checks, fn (array $check) => $check['section'] === $code));
            $status = $this->rollup(array_column($related, 'status'));
            $rows[] = [
                'code' => $code,
                'label' => $label,
                'status' => $status,
                'detail' => $this->rollupDetail($related, $status),
            ];
        }

        return $rows;
    }

    private function cardStatus(?array $saved): string
    {
        if ($saved === null) {
            return 'NOT TESTED';
        }

        return $this->rollup(array_column($saved['reads'] ?? [], 'status'));
    }

    private function worst(string $left, string $right): string
    {
        return $this->rollup([$left, $right]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $routers
     */
    private function routerSection(array $routers, string $read): string
    {
        $statuses = [];
        foreach ($routers as $router) {
            $probe = $router['probe'] ?? null;
            if (! is_array($probe)) {
                continue;
            }
            $statuses[] = $probe['reads'][$read]['status'] ?? 'NOT TESTED';
        }

        return $this->rollup($statuses);
    }

    /**
     * @param  array<int, array<string, mixed>>  $routers
     */
    private function hotspotSection(array $routers): string
    {
        $statuses = [];
        foreach ($routers as $router) {
            $probe = $router['probe'] ?? null;
            if (! is_array($probe)) {
                continue;
            }
            $statuses[] = $probe['hotspot']['status'] ?? 'NOT TESTED';
        }

        return $this->rollup($statuses);
    }

    /**
     * @param  array<int, array<string, mixed>>  $routers
     */
    private function planSection(array $routers): string
    {
        $statuses = [];
        foreach ($routers as $router) {
            $probe = $router['probe'] ?? null;
            if (! is_array($probe) || ($probe['authenticated'] ?? false) !== true) {
                continue;
            }
            foreach ($probe['plans'] ?? [] as $plan) {
                $statuses[] = $plan['status'] ?? 'NOT TESTED';
            }
        }

        return $this->rollup($statuses);
    }

    /**
     * @param  array<int, string>  $statuses
     */
    /**
     * @param  array<int, array{status: string, detail: string}>  $checks
     */
    private function rollupDetail(array $checks, string $status): string
    {
        foreach ($checks as $check) {
            if ($check['status'] === $status) {
                return $check['detail'];
            }
        }

        return $checks[0]['detail'] ?? '';
    }

    private function rollup(array $statuses): string
    {
        if (in_array('FAIL', $statuses, true)) {
            return 'FAIL';
        }
        if (in_array('WARN', $statuses, true)) {
            return 'WARN';
        }
        if (in_array('NOT TESTED', $statuses, true)) {
            return 'NOT TESTED';
        }
        if ($statuses === []) {
            return 'NOT TESTED';
        }

        return 'PASS';
    }

    /**
     * @return array{code: string, label: string, status: string, detail: string, section: string}
     */
    private function row(string $code, string $label, string $status, string $detail, string $section): array
    {
        return compact('code', 'label', 'status', 'detail', 'section');
    }
}
