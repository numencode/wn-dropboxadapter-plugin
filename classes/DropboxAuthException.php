<?php

declare(strict_types=1);

namespace NumenCode\DropboxAdapter\Classes;

use RuntimeException;

/**
 * Thrown when the Dropbox disk is misconfigured or Dropbox rejects an OAuth2 request.
 */
class DropboxAuthException extends RuntimeException
{
}
