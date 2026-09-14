<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Throwable;
use UnexpectedValueException;

final class Application
{
    public function __construct(
        private readonly UrlMatcherInterface $urlMatcher,
        private readonly ExceptionHandler $exceptionHandler,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            $context = new RequestContext();
            $context->fromRequest($request);
            $this->urlMatcher->setContext($context);

            $attributes = $this->urlMatcher->match($request->getPathInfo());
            $request->attributes->add($attributes);

            $controller = $attributes['_controller'] ?? null;

            if (!is_callable($controller)) {
                throw new UnexpectedValueException('The matched route has no callable controller.');
            }

            $response = $controller($request);

            if (!$response instanceof Response) {
                throw new UnexpectedValueException('Controllers must return a Response instance.');
            }

            return $response;
        } catch (Throwable $exception) {
            return $this->exceptionHandler->handle($request, $exception);
        }
    }
}
