<?php

namespace Algebrakit\SDK\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a request to the Algebrakit API fails: an error response, a network error or a timeout.
 *
 * Extends RuntimeException, so code that catches RuntimeException keeps working.
 */
class AlgebrakitApiException extends RuntimeException
{
    public function __construct(string $message, private ?int $statusCode = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The HTTP status code of the API's response, or null if there was no response (network error or timeout).
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}
