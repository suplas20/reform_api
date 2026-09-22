<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Repository\HelpRepository;
use DateTimeImmutable;
use DateTimeZone;

final class HelpService
{
    public function __construct(private readonly HelpRepository $repository)
    {
    }

    public function categories(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'sortOrder' => (int) $row['sort_order'],
        ], $this->repository->findCategories());
    }

    public function faqs(?int $categoryId, ?string $keyword, int $page, int $perPage): array
    {
        $total = $this->repository->countFaqs($categoryId, $keyword);

        return [
            'items' => array_map([self::class, 'mapFaq'], $this->repository->findFaqPage($categoryId, $keyword, $perPage, ($page - 1) * $perPage)),
            'total' => $total,
            'totalPages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
        ];
    }

    public function faq(int $id): array
    {
        $row = $this->repository->findFaq($id);

        if ($row === null) {
            throw new NotFoundException('FAQ_NOT_FOUND', 'FAQ를 찾을 수 없습니다.');
        }

        return self::mapFaq($row);
    }

    private static function mapFaq(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'categoryId' => (int) $row['category_id'],
            'question' => (string) $row['question'],
            'answerHtml' => (string) $row['answer_html'],
            'sortOrder' => (int) $row['sort_order'],
            'updatedAt' => (new DateTimeImmutable((string) $row['updated_at'], new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
