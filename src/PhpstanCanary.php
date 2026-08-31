<?php

namespace motuslogistik\Metrics;

/**
 * Temporary: deliberate PHPStan errors to verify GitHub PR annotations. Delete me.
 */
class PhpstanCanary
{
    public function returnsWrongType(): string
    {
        return 42;
    }

    public function callsMissingMethod(): void
    {
        $this->thisMethodDoesNotExist();
    }
}
