<?php

declare(strict_types=1);

namespace App\Controller\Api;

use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiDocumentationController
{
    /**
     * @var array<string, array{file: string, contentType: string}>
     */
    private const ASSETS = [
        'swagger-ui.css' => [
            'file' => 'swagger-ui.css',
            'contentType' => 'text/css; charset=UTF-8',
        ],
        'swagger-ui-bundle.js' => [
            'file' => 'swagger-ui-bundle.js',
            'contentType' => 'application/javascript; charset=UTF-8',
        ],
        'swagger-ui-standalone-preset.js' => [
            'file' => 'swagger-ui-standalone-preset.js',
            'contentType' => 'application/javascript; charset=UTF-8',
        ],
    ];

    public function __construct(
        private readonly string $specificationPath,
        private readonly string $swaggerAssetDirectory,
    ) {
    }

    public function index(Request $request): Response
    {
        $html = <<<'HTML'
            <!doctype html>
            <html lang="ko">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Reform Platform API</title>
                <link rel="stylesheet" href="/api/docs/assets/swagger-ui.css">
            </head>
            <body>
                <div id="swagger-ui"></div>
                <script src="/api/docs/assets/swagger-ui-bundle.js"></script>
                <script src="/api/docs/assets/swagger-ui-standalone-preset.js"></script>
                <script src="/api/docs/assets/initializer.js"></script>
            </body>
            </html>
            HTML;

        return new Response($html, 200, $this->documentationHeaders('text/html; charset=UTF-8'));
    }

    public function specification(Request $request): Response
    {
        return new Response(
            $this->readFile($this->specificationPath),
            200,
            $this->documentationHeaders('application/vnd.oai.openapi+json; charset=UTF-8'),
        );
    }

    public function asset(Request $request): Response
    {
        $asset = (string) $request->attributes->get('asset');

        if ($asset === 'initializer.js') {
            return new Response(
                <<<'JAVASCRIPT'
                    window.addEventListener('load', function () {
                        SwaggerUIBundle({
                            url: '/api/docs/openapi.json',
                            dom_id: '#swagger-ui',
                            deepLinking: true,
                            presets: [
                                SwaggerUIBundle.presets.apis,
                                SwaggerUIStandalonePreset
                            ],
                            layout: 'StandaloneLayout'
                        });
                    });
                    JAVASCRIPT,
                200,
                $this->assetHeaders('application/javascript; charset=UTF-8'),
            );
        }

        $definition = self::ASSETS[$asset] ?? null;

        if ($definition === null) {
            throw new RuntimeException('Unsupported Swagger UI asset.');
        }

        return new Response(
            $this->readFile($this->swaggerAssetDirectory . '/' . $definition['file']),
            200,
            $this->assetHeaders($definition['contentType']),
        );
    }

    /**
     * @return array<string, string>
     */
    private function documentationHeaders(string $contentType): array
    {
        return [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-store',
            'Content-Security-Policy' => "default-src 'none'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'",
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function assetHeaders(string $contentType): array
    {
        return [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    private function readFile(string $path): string
    {
        $content = file_get_contents($path);

        if (!is_string($content)) {
            throw new RuntimeException('Unable to read API documentation asset.');
        }

        return $content;
    }
}
