<?php

namespace App\Twig;

use App\Service\AlertingService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AlertingExtension extends AbstractExtension
{
    public function __construct(
        private readonly AlertingService $alertingService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_proactive_alerts', [$this, 'getAlerts']),
        ];
    }

    /**
     * @return array{total_alerts: int, critical_count: int, warning_count: int, alerts: array<int, mixed>}
     */
    public function getAlerts(): array
    {
        return $this->alertingService->getActiveAlerts();
    }
}

