<?php

declare(strict_types=1);

namespace Tegro\Purchase\Postback;

/**
 * Small DTO returned by the Handler — the entrypoint converts this into an
 * HTTP response. Keeping it as a value object means the same Handler can be
 * exercised from PHPUnit without spinning up a web server.
 */
final readonly class Response
{
    public function __construct(
        public int $statusCode,
        public string $body,
    ) {
    }
}
