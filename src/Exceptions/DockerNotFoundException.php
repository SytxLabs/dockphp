<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Exceptions;

/**
 * Thrown for HTTP 404 responses, e.g. inspecting a container, image,
 * network or volume that does not exist.
 */
class DockerNotFoundException extends DockerApiException
{
}
