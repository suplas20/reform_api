<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeZone;

final class UserService
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    /** @return array<string, mixed> */
    public function profile(int $userId): array
    {
        $user = $this->userRepository->findActiveById($userId);

        if ($user === null) {
            throw new NotFoundException('USER_NOT_FOUND', '사용자를 찾을 수 없습니다.');
        }

        return [
            'id' => (int) $user['id'],
            'name' => self::nullableString($user['name']),
            'nickname' => self::nullableString($user['nickname']),
            'tagline' => self::nullableString($user['tagline']),
            'email' => self::nullableString($user['email']),
            'phone' => self::nullableString($user['phone']),
            'avatarUrl' => null,
            'socialProviders' => $this->userRepository->findSocialProviders($userId),
            'createdAt' => self::utcDateTime((string) $user['created_at']),
        ];
    }

    /** @param array<string, string|null> $changes */
    public function updateProfile(int $userId, array $changes): array
    {
        if (array_key_exists('avatarMediaId', $changes)) {
            $mediaId = $changes['avatarMediaId'];
            unset($changes['avatarMediaId']);

            if ($mediaId === null) {
                $changes['profileImage'] = null;
            } else {
                $path = $this->userRepository->findCompletedProfileMediaPath($userId, $mediaId);

                if ($path === null) {
                    throw new ValidationException('MEDIA_INVALID', '프로필 이미지를 확인해주세요.');
                }

                $changes['profileImage'] = $path;
            }
        }

        $this->userRepository->updateProfile($userId, $changes, gmdate('Y-m-d H:i:s'));

        return $this->profile($userId);
    }

    /** @return array<string, int> */
    public function activityCounts(int $userId): array
    {
        return $this->userRepository->activityCounts($userId);
    }

    public function deactivate(int $userId): void
    {
        $this->userRepository->deactivate($userId, gmdate('Y-m-d H:i:s'));
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function utcDateTime(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
