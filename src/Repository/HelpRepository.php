<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class HelpRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findCategories(): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, name, sort_order FROM help_categories WHERE status = :status ORDER BY sort_order ASC, id ASC',
        );
        $statement->execute(['status' => 'ACTIVE']);

        return $statement->fetchAll();
    }

    public function countFaqs(?int $categoryId, ?string $keyword): int
    {
        [$conditions, $parameters] = $this->faqConditions($categoryId, $keyword);
        $statement = $this->connection->prepare(sprintf(
            'SELECT COUNT(*) FROM help_faqs WHERE status = :status %s',
            implode(' ', $conditions),
        ));
        $statement->execute(['status' => 'PUBLISHED', ...$parameters]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function findFaqPage(?int $categoryId, ?string $keyword, int $limit, int $offset): array
    {
        [$conditions, $parameters] = $this->faqConditions($categoryId, $keyword);
        $statement = $this->connection->prepare(sprintf(
            <<<'SQL'
                SELECT id, category_id, question, answer_html, sort_order, updated_at
                FROM help_faqs
                WHERE status = :status %s
                ORDER BY sort_order ASC, id ASC
                LIMIT :limit OFFSET :offset
                SQL,
            implode(' ', $conditions),
        ));
        $statement->bindValue(':status', 'PUBLISHED');

        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findFaq(int $id): ?array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT id, category_id, question, answer_html, sort_order, updated_at
                FROM help_faqs WHERE id = :id AND status = :status LIMIT 1
                SQL,
        );
        $statement->execute(['id' => $id, 'status' => 'PUBLISHED']);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array{list<string>, array<string, mixed>} */
    private function faqConditions(?int $categoryId, ?string $keyword): array
    {
        $conditions = [];
        $parameters = [];

        if ($categoryId !== null) {
            $conditions[] = 'AND category_id = :category_id';
            $parameters['category_id'] = $categoryId;
        }

        if ($keyword !== null) {
            $conditions[] = "AND (question LIKE :question_keyword ESCAPE '!' OR answer_html LIKE :answer_keyword ESCAPE '!')";
            $parameters['question_keyword'] = SqlLikePattern::contains($keyword);
            $parameters['answer_keyword'] = SqlLikePattern::contains($keyword);
        }

        return [$conditions, $parameters];
    }
}
