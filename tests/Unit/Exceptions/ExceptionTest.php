<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Exceptions;

use CoenJacobs\Mozart\Exceptions\ConfigurationException;
use CoenJacobs\Mozart\Exceptions\FileOperationException;
use CoenJacobs\Mozart\Exceptions\MozartException;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExceptionTest extends TestCase
{
    #[Test]
    public function mozart_exception_is_throwable(): void
    {
        $exception = new MozartException('Test message');

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    #[Test]
    public function configuration_exception_extends_mozart_exception(): void
    {
        $exception = new ConfigurationException('Config error');

        $this->assertInstanceOf(MozartException::class, $exception);
        $this->assertEquals('Config error', $exception->getMessage());
    }

    #[Test]
    public function file_operation_exception_extends_mozart_exception(): void
    {
        $exception = new FileOperationException('File error');

        $this->assertInstanceOf(MozartException::class, $exception);
        $this->assertEquals('File error', $exception->getMessage());
    }

    #[Test]
    public function exceptions_can_have_previous_exception(): void
    {
        $previous = new RuntimeException('Previous error');
        $exception = new FileOperationException('File error', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}

