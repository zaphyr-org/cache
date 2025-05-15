<?php

declare(strict_types=1);

namespace Zaphyr\Cache\Exceptions;

use Exception;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class InvalidArgumentException extends Exception implements PsrInvalidArgumentException
{
}
