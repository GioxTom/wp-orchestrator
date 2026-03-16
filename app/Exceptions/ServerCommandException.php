<?php

namespace App\Exceptions;

use RuntimeException;

class ServerCommandException extends RuntimeException
{
    public function __construct(
        string $command,
        string $output,
        int $exitCode,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            "Command failed (exit {$exitCode}): {$command}\nOutput: {$output}",
            $exitCode,
            $previous
        );
    }
}
