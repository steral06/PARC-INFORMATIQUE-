<?php

namespace App\EventListener;

use App\Service\AuditLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

#[AsEventListener(event: RequestEvent::class, priority: 50)]
class RateLimitListener
{
    public function __construct(
        #[Target('global_ip')]
        private readonly RateLimiterFactoryInterface $globalIpLimiter,
        #[Target('api_search')]
        private readonly RateLimiterFactoryInterface $apiSearchLimiter,
        private readonly AuditLogger $auditLogger,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Ignorer les fichiers statiques, assets, profiler, barre de debug, favicon
        if (
            str_starts_with($path, '/assets') ||
            str_starts_with($path, '/_') ||
            $path === '/favicon.ico' ||
            str_starts_with($path, '/build')
        ) {
            return;
        }

        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        // 1. Limiter spécifique pour les recherches dynamiques rapides
        if (str_starts_with($path, '/search/quick') || str_starts_with($path, '/api/')) {
            $limiter = $this->apiSearchLimiter->create($clientIp);
            $limit = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                $retryAfter = $limit->getRetryAfter() ? max(1, $limit->getRetryAfter()->getTimestamp() - time()) : 60;
                $this->logRateLimitViolation($clientIp, $path, 'API/Recherche', $retryAfter);

                $event->setResponse(new JsonResponse([
                    'error' => 'Trop de requêtes de recherche. Protection anti-DoS active.',
                    'retry_after' => $retryAfter,
                ], Response::HTTP_TOO_MANY_REQUESTS, [
                    'Retry-After' => (string) $retryAfter,
                ]));
                return;
            }
        }

        // 2. Limiteur global par IP pour l'ensemble du trafic
        $limiter = $this->globalIpLimiter->create($clientIp);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter() ? max(1, $limit->getRetryAfter()->getTimestamp() - time()) : 60;
            $this->logRateLimitViolation($clientIp, $path, 'Trafic Global', $retryAfter);

            if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
                $response = new JsonResponse([
                    'error' => 'Limite de requêtes dépassée. Veuillez patienter avant de réessayer.',
                    'retry_after' => $retryAfter,
                ], Response::HTTP_TOO_MANY_REQUESTS, [
                    'Retry-After' => (string) $retryAfter,
                ]);
            } else {
                $response = new Response(
                    $this->renderRateLimitHtml($clientIp, $retryAfter),
                    Response::HTTP_TOO_MANY_REQUESTS,
                    [
                        'Retry-After' => (string) $retryAfter,
                        'Content-Type' => 'text/html; charset=UTF-8',
                    ]
                );
            }

            $event->setResponse($response);
        }
    }

    private function logRateLimitViolation(string $ip, string $path, string $type, int $retryAfter): void
    {
        $this->logger->warning(sprintf('Rate limit exceeded for IP %s on path %s (%s). Retry after %ds.', $ip, $path, $type, $retryAfter));

        try {
            $this->auditLogger->log(
                action: 'RATE_LIMIT_EXCEEDED',
                level: 'AVERTISSEMENT',
                status: 'ECHEC',
                username: null,
                ipAddress: $ip,
                entityType: 'SECURITY',
                entityId: null,
                description: sprintf('Protection anti-DoS déclenchée : seuil de requêtes (%s) dépassé pour l\'IP %s sur %s. Blocage temporaire %d s.', $type, $ip, $path, $retryAfter),
                details: [
                    'path' => $path,
                    'type' => $type,
                    'retry_after' => $retryAfter,
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to log rate limit to audit_log: ' . $e->getMessage());
        }
    }

    private function renderRateLimitHtml(string $ip, int $retryAfter): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>429 — Trop de requêtes | G-PARC</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        body {
            background: #0b0f19;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }
        .card {
            background: rgba(17, 24, 39, 0.95);
            border: 1px solid rgba(228, 112, 0, 0.25);
            border-radius: 1.25rem;
            max-width: 540px;
            width: 100%;
            padding: 2.5rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 40px rgba(228, 112, 0, 0.1);
        }
        .icon-wrap {
            width: 72px;
            height: 72px;
            background: rgba(228, 112, 0, 0.15);
            border: 2px solid #E47000;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: #E47000;
            font-size: 2rem;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.75rem;
        }
        p {
            color: #94a3b8;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.25rem;
        }
        .meta-box {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            text-align: left;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            padding: 0.25rem 0;
            color: #cbd5e1;
        }
        .meta-label { color: #64748b; }
        .countdown {
            font-size: 1.25rem;
            font-weight: 700;
            color: #E47000;
        }
        .btn-reload {
            display: inline-block;
            background: #E47000;
            color: #ffffff;
            text-decoration: none;
            padding: 0.75rem 1.75rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            border: none;
            transition: background 0.2s;
        }
        .btn-reload:hover {
            background: #c86200;
        }
        .footer-note {
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap">🛡️</div>
        <h1>Protection Anti-Attaque Active</h1>
        <p>
            Un volume inhabituel de requêtes a été détecté depuis votre adresse réseau.
            Pour garantir la stabilité et la sécurité du parc GTAC2A, l'accès est temporairement suspendu.
        </p>

        <div class="meta-box">
            <div class="meta-row">
                <span class="meta-label">Code d'erreur :</span>
                <strong>HTTP 429 Too Many Requests</strong>
            </div>
            <div class="meta-row">
                <span class="meta-label">Adresse IP :</span>
                <code>{$ip}</code>
            </div>
            <div class="meta-row">
                <span class="meta-label">Reprise automatique dans :</span>
                <span class="countdown" id="timer">{$retryAfter}s</span>
            </div>
        </div>

        <button class="btn-reload" onclick="window.location.reload()">Réessayer maintenant</button>

        <div class="footer-note">
            G-PARC — Système de Gestion de Parc Informatique GTAC2A-IARDT
        </div>
    </div>

    <script>
        let seconds = {$retryAfter};
        const timerEl = document.getElementById('timer');
        const interval = setInterval(() => {
            seconds--;
            if (seconds <= 0) {
                clearInterval(interval);
                timerEl.textContent = 'Prêt !';
                window.location.reload();
            } else {
                timerEl.textContent = seconds + 's';
            }
        }, 1000);
    </script>
</body>
</html>
HTML;
    }
}

