<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Handler;

/**
 * How a pipeline handler turns its Output into a response: full render, partial, JSON, redirect or download.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
enum OutputType: string
{
    case Render = 'render';
    case Partial = 'partial';
    case Json = 'json';
    case Redirect = 'redirect';
    case Download = 'download';
}
