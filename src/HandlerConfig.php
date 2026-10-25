<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Handler;

/**
 * Handler settings: namespace prefix stripped to locate views, views and layout aliases, debug flag.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class HandlerConfig
{
    public function __construct(
        public readonly string $handlerNamespacePrefix = 'App\\Handlers\\',
        public readonly string $viewsAlias = '@src/Views',
        public readonly string $layoutAlias = '@src/Views/Layouts/main.php',
        public readonly bool $debug = false,
    ) {
    }
}
