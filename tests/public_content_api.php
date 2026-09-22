<?php

declare(strict_types=1);

use App\Controller\Api\V1\ReviewController;
use App\Controller\Api\V1\WorkCaseController;
use App\Http\Application;
use App\Http\ExceptionHandler;
use App\Repository\ReviewReadRepository;
use App\Repository\WorkCaseReadRepository;
use App\Service\ReviewService;
use App\Service\WorkCaseService;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertPublicContentValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, sprintf(
            "%s\nExpected: %s\nActual: %s\n",
            $message,
            var_export($expected, true),
            var_export($actual, true),
        ));
        exit(1);
    }
}

$workCaseRepository = new class implements WorkCaseReadRepository {
    /** @var array<string, int|null> */
    public array $lastCriteria = [];

    public function countPublic(string $publishedAt, array $criteria): int
    {
        $this->lastCriteria = $criteria;

        return 2;
    }

    public function findPublicPage(string $publishedAt, int $limit, int $offset, array $criteria): array
    {
        return array_slice([
            [
                'id' => 101,
                'business_id' => 10,
                'title' => '가죽 가방 복원',
                'slug' => 'bag-restoration',
                'description' => '상세 설명',
                'published_at' => '2026-09-20 03:00:00',
                'category_id' => 1,
                'category_name' => '가방',
                'brand_id' => 5,
                'brand_name' => '브랜드',
                'business_name' => '리폼 공방',
            ],
            [
                'id' => 102,
                'business_id' => 10,
                'title' => '신발 수선',
                'slug' => 'shoe-repair',
                'description' => null,
                'published_at' => '2026-09-19 03:00:00',
                'category_id' => null,
                'category_name' => null,
                'brand_id' => null,
                'brand_name' => null,
                'business_name' => '리폼 공방',
            ],
        ], $offset, $limit);
    }

    public function findPublicById(int $id, string $publishedAt): ?array
    {
        if ($id !== 101) {
            return null;
        }

        return [
            'id' => 101,
            'business_id' => 10,
            'title' => '가죽 가방 복원',
            'slug' => 'bag-restoration',
            'description' => '상세 설명',
            'published_at' => '2026-09-20 03:00:00',
            'category_id' => 1,
            'category_name' => '가방',
            'brand_id' => 5,
            'brand_name' => '브랜드',
            'business_name' => '리폼 공방',
        ];
    }

    public function findActiveServicesByWorkCaseIds(array $workCaseIds): array
    {
        return in_array(101, $workCaseIds, true)
            ? [['work_case_id' => 101, 'id' => 7, 'name' => '가죽 복원']]
            : [];
    }
};

$reviewRepository = new class implements ReviewReadRepository {
    /** @var array<string, mixed> */
    public array $lastCriteria = [];

    public function publicBusinessExists(int $businessId, string $publishedAt): bool
    {
        return $businessId === 10;
    }

    public function countPublicByBusinessId(int $businessId, array $criteria): int
    {
        $this->lastCriteria = $criteria;

        return 1;
    }

    public function findPublicPageByBusinessId(int $businessId, int $limit, int $offset, array $criteria): array
    {
        return [[
            'id' => 9001,
            'rating' => 5,
            'content' => '만족합니다.',
            'created_at' => '2026-09-20 06:30:00',
            'updated_at' => '2026-09-20 06:30:00',
            'user_id' => 77,
            'user_name' => '김리폼',
            'profile_image' => '/private/path.jpg',
        ]];
    }

    public function summarizePublicByBusinessId(int $businessId): array
    {
        return [
            'average_rating' => '4.80',
            'review_count' => 5,
            'rating_1' => 0,
            'rating_2' => 0,
            'rating_3' => 0,
            'rating_4' => 1,
            'rating_5' => 4,
        ];
    }
};

$workCaseController = new WorkCaseController(new WorkCaseService($workCaseRepository));
$reviewController = new ReviewController(new ReviewService($reviewRepository));
$routes = new RouteCollection();
$routes->add('work-cases.index', new Route('/api/v1/work-cases', ['_controller' => [$workCaseController, 'index']], methods: ['GET']));
$routes->add('work-cases.show', new Route('/api/v1/work-cases/{id}', ['_controller' => [$workCaseController, 'show']], ['id' => '\\d+'], methods: ['GET']));
$routes->add('reviews.index', new Route('/api/v1/businesses/{businessId}/reviews', ['_controller' => [$reviewController, 'index']], ['businessId' => '\\d+'], methods: ['GET']));
$routes->add('reviews.summary', new Route('/api/v1/businesses/{businessId}/review-summary', ['_controller' => [$reviewController, 'summary']], ['businessId' => '\\d+'], methods: ['GET']));
$logger = new Logger('test');
$logger->pushHandler(new NullHandler());
$application = new Application(new UrlMatcher($routes, new RequestContext()), new ExceptionHandler($logger));

$workCaseList = $application->handle(Request::create('/api/v1/work-cases?businessId=10&limit=1', 'GET'));
$workCaseListPayload = json_decode($workCaseList->getContent(), true, flags: JSON_THROW_ON_ERROR);
assertPublicContentValue(200, $workCaseList->getStatusCode(), 'Work Case list must return 200.');
assertPublicContentValue(101, $workCaseListPayload['data'][0]['id'], 'Work Case list item is invalid.');
assertPublicContentValue('가죽 복원', $workCaseListPayload['data'][0]['services'][0]['name'], 'Work Case services are invalid.');
assertPublicContentValue(null, $workCaseListPayload['data'][0]['beforeImageUrl'], 'Storage path must not be exposed.');
assertPublicContentValue(1, $workCaseListPayload['meta']['perPage'], 'The limit alias must set perPage.');
assertPublicContentValue(10, $workCaseRepository->lastCriteria['businessId'], 'Business filter is invalid.');

$workCaseDetail = $application->handle(Request::create('/api/v1/work-cases/101', 'GET'));
$workCaseDetailPayload = json_decode($workCaseDetail->getContent(), true, flags: JSON_THROW_ON_ERROR);
assertPublicContentValue('리폼 공방', $workCaseDetailPayload['data']['business']['name'], 'Work Case Business is invalid.');
assertPublicContentValue([], $workCaseDetailPayload['data']['images'], 'Work Case images must remain deferred.');

$missingWorkCase = $application->handle(Request::create('/api/v1/work-cases/999', 'GET'));
assertPublicContentValue(404, $missingWorkCase->getStatusCode(), 'Missing Work Case must return 404.');

$reviewList = $application->handle(Request::create('/api/v1/businesses/10/reviews?sort=ratingHigh&hasImages=true&limit=10', 'GET'));
$reviewListPayload = json_decode($reviewList->getContent(), true, flags: JSON_THROW_ON_ERROR);
assertPublicContentValue(200, $reviewList->getStatusCode(), 'Review list must return 200.');
assertPublicContentValue(9001, $reviewListPayload['data'][0]['id'], 'Review list item is invalid.');
assertPublicContentValue(null, $reviewListPayload['data'][0]['user']['profileImage'], 'Private profile path must not be exposed.');
assertPublicContentValue(4.8, $reviewListPayload['meta']['summary']['averageRating'], 'Review summary is invalid.');
assertPublicContentValue('ratingDesc', $reviewRepository->lastCriteria['sort'], 'App sort alias is invalid.');
assertPublicContentValue(true, $reviewRepository->lastCriteria['hasImages'], 'Review image filter is invalid.');

$reviewSummary = $application->handle(Request::create('/api/v1/businesses/10/review-summary', 'GET'));
$reviewSummaryPayload = json_decode($reviewSummary->getContent(), true, flags: JSON_THROW_ON_ERROR);
assertPublicContentValue(5, $reviewSummaryPayload['data']['total'], 'Review summary total is invalid.');
assertPublicContentValue(0.8, $reviewSummaryPayload['data']['distribution'][0]['ratio'], 'Review summary ratio is invalid.');

$invalidReviewSort = $application->handle(Request::create('/api/v1/businesses/10/reviews?sort=helpful', 'GET'));
assertPublicContentValue(422, $invalidReviewSort->getStatusCode(), 'Unsupported Review sort must return 422.');

$missingBusinessReviews = $application->handle(Request::create('/api/v1/businesses/999/reviews', 'GET'));
assertPublicContentValue(404, $missingBusinessReviews->getStatusCode(), 'Missing Business Reviews must return 404.');

fwrite(STDOUT, "Public content API tests passed.\n");
