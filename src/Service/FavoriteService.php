<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\TransactionManager;
use App\Exception\NotFoundException;
use App\Repository\BusinessReadRepository;
use App\Repository\FavoriteRepository;

final class FavoriteService
{
    public function __construct(
        private readonly FavoriteRepository $favoriteRepository,
        private readonly BusinessReadRepository $businessRepository,
        private readonly BusinessService $businessService,
        private readonly TransactionManager $transactionManager,
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, total: int, totalPages: int} */
    public function list(int $userId, int $page, int $perPage): array
    {
        $total = $this->favoriteRepository->countByUserId($userId);
        $businessIds = $this->favoriteRepository->findBusinessIdsByUserId(
            $userId,
            $perPage,
            ($page - 1) * $perPage,
        );
        $items = array_map(
            static fn (array $item): array => [...$item, 'isFavorite' => true],
            $this->businessService->getPublicSummariesByIds($businessIds),
        );

        return [
            'items' => $items,
            'total' => $total,
            'totalPages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
        ];
    }

    public function add(int $userId, int $businessId): void
    {
        if ($this->businessRepository->findPublicDetailById($businessId, gmdate('Y-m-d H:i:s')) === null) {
            throw new NotFoundException('BUSINESS_NOT_FOUND', '업체를 찾을 수 없습니다.');
        }

        $this->transactionManager->run(function () use ($userId, $businessId): void {
            $now = gmdate('Y-m-d H:i:s');
            $this->favoriteRepository->add($userId, $businessId, $now);
            $this->favoriteRepository->refreshBusinessCount($businessId, $now);
        });
    }

    public function remove(int $userId, int $businessId): void
    {
        $this->transactionManager->run(function () use ($userId, $businessId): void {
            $now = gmdate('Y-m-d H:i:s');
            $this->favoriteRepository->remove($userId, $businessId);
            $this->favoriteRepository->refreshBusinessCount($businessId, $now);
        });
    }
}
