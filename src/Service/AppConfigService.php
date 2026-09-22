<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ServiceUnavailableException;

final class AppConfigService
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function get(string $platform, string $appVersion): array
    {
        if ($this->config['minimum_version'] === '' || $this->config['latest_version'] === '') {
            throw new ServiceUnavailableException('APP_CONFIG_NOT_CONFIGURED', '앱 설정이 완료되지 않았습니다.');
        }

        return [
            'minimumVersion' => $this->config['minimum_version'],
            'latestVersion' => $this->config['latest_version'],
            'forceUpdate' => version_compare($appVersion, $this->config['minimum_version'], '<'),
            'maintenance' => [
                'enabled' => $this->config['maintenance_enabled'],
                'message' => $this->config['maintenance_message'],
            ],
            'customerCenter' => [
                'phone' => $this->config['customer_center_phone'],
                'hours' => $this->config['customer_center_hours'],
            ],
            'terms' => [
                'termsOfServiceUrl' => $this->config['terms_url'],
                'privacyPolicyUrl' => $this->config['privacy_url'],
                'openSourceLicenseUrl' => $this->config['licenses_url'],
            ],
        ];
    }
}
