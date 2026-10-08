<?php

declare(strict_types=1);

namespace App\Auth;

interface SocialIdentityVerifier
{
    /**
     * @return array{providerUserId: string, email: ?string, name: ?string}
     */
    public function verify(
        string $provider,
        ?string $authorizationCode,
        ?string $idToken,
        string $redirectUri,
        ?string $state = null,
    ): array;
}
