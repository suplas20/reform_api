<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\TransactionManager;
use App\Repository\AdministrativeRegionSyncRepository;
use RuntimeException;

final class AdministrativeRegionSyncService
{
    /** @param array<string, mixed> $rules */
    public function __construct(
        private readonly AdministrativeRegionSyncRepository $repository,
        private readonly TransactionManager $transactions,
        private readonly AdministrativeRegionSyncPlanner $planner,
        private readonly string $snapshotKey,
        private readonly array $rules,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(bool $apply): array
    {
        $operation = function () use ($apply): array {
            $snapshot = $this->repository->snapshot($this->snapshotKey);
            if ($snapshot['revision'] !== $this->rules['revision']) {
                throw new RuntimeException('Source revision changed; review synchronization mappings first.');
            }
            $id = (int) $snapshot['id'];
            $revision = $snapshot['revision'];
            $plan = $this->planner->plan($this->repository->source($id, $revision),
                $this->repository->regions(), $this->repository->currentDongs(), $this->rules);
            if ($apply) {
                $ids = [];
                foreach ($plan['create'] as $region) {
                    // Rebuild after each insert so references to newly inserted parents resolve.
                    $parentId = str_starts_with($region['parent'], 'id:')
                        ? (int) substr($region['parent'], 3) : $ids[$region['parent']];
                    $ids[$region['code']] = $this->repository->insert($region, $parentId, gmdate('Y-m-d H:i:s'));
                }
                $mapping = [];
                foreach ($plan['mapping'] as $code => $ref) {
                    $mapping[$code] = str_starts_with($ref, 'id:') ? (int) substr($ref, 3) : $ids[$ref];
                }
                $this->repository->linkSource($id, $revision, $mapping);
            }
            unset($plan['create'], $plan['mapping']);
            return ['applied' => $apply, 'snapshotKey' => $this->snapshotKey, 'revision' => $revision, ...$plan];
        };
        return $apply ? $this->transactions->run($operation) : $operation();
    }
}
