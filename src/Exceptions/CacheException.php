<?php

declare(strict_types=1);

namespace Zaphyr\Cache\Exceptions;

use Exception;
use Psr\SimpleCache\CacheException as PsrCacheException;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class CacheException extends Exception implements PsrCacheException
{
}
