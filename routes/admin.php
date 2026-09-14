<?php

declare(strict_types=1);

use Symfony\Component\Routing\RouteCollection;

return static function (RouteCollection $routes): void {
    // Admin routes remain separate from public web and mobile API routes.
};
