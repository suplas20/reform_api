<?php

declare(strict_types=1);

namespace App\Auth;

final class ProviderSocialIdentityVerifier implements SocialIdentityVerifier
{
    /** @param array<string, SocialIdentityVerifier> $providers */
    public function __construct(private readonly array $providers)
    {
    }

    public function verify(string $provider, ?string $authorizationCode, ?string $idToken, string $redirectUri, ?string $state = null): array
    {
        return ($this->providers[$provider] ?? new UnavailableSocialIdentityVerifier())->verify(
            $provider, $authorizationCode, $idToken, $redirectUri, $state,
        );
    }
}
