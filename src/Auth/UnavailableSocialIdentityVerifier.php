<?php

declare(strict_types=1);

namespace App\Auth;

use App\Exception\ServiceUnavailableException;

final class UnavailableSocialIdentityVerifier implements SocialIdentityVerifier
{
    public function verify(
        string $provider,
        ?string $authorizationCode,
        ?string $idToken,
        string $redirectUri,
    ): array {
        throw new ServiceUnavailableException(
            'SOCIAL_PROVIDER_NOT_CONFIGURED',
            '소셜 로그인 설정이 완료되지 않았습니다.',
        );
    }
}
