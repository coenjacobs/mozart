<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\PhpSymbols;

use CoenJacobs\Mozart\PhpSymbols\BuiltInSymbols;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BuiltInSymbolsTest extends TestCase
{
    private BuiltInSymbols $symbols;

    protected function setUp(): void
    {
        $this->symbols = new BuiltInSymbols();
    }

    #[Test]
    public function it_recognizes_built_in_classes(): void
    {
        $this->assertTrue($this->symbols->isBuiltInClass('stdClass'));
        $this->assertTrue($this->symbols->isBuiltInClass('Exception'));
        $this->assertTrue($this->symbols->isBuiltInClass('ValueError'));
    }

    #[Test]
    public function it_does_not_recognize_unknown_classes(): void
    {
        $this->assertFalse($this->symbols->isBuiltInClass('My_Custom_Class'));
        $this->assertFalse($this->symbols->isBuiltInClass('WP_Query'));
    }

    #[Test]
    public function it_recognizes_built_in_interfaces(): void
    {
        $this->assertTrue($this->symbols->isBuiltInInterface('Iterator'));
        $this->assertTrue($this->symbols->isBuiltInInterface('Countable'));
        $this->assertTrue($this->symbols->isBuiltInInterface('Stringable'));
    }

    #[Test]
    public function it_does_not_recognize_unknown_interfaces(): void
    {
        $this->assertFalse($this->symbols->isBuiltInInterface('My_Custom_Interface'));
    }

    #[Test]
    public function it_recognizes_built_in_functions(): void
    {
        $this->assertTrue($this->symbols->isBuiltInFunction('strlen'));
        $this->assertTrue($this->symbols->isBuiltInFunction('array_map'));
        $this->assertTrue($this->symbols->isBuiltInFunction('class_exists'));
    }

    #[Test]
    public function it_does_not_recognize_unknown_functions(): void
    {
        $this->assertFalse($this->symbols->isBuiltInFunction('my_custom_function'));
    }

    #[Test]
    public function it_recognizes_built_in_constants(): void
    {
        $this->assertTrue($this->symbols->isBuiltInConstant('PHP_EOL'));
        $this->assertTrue($this->symbols->isBuiltInConstant('TRUE'));
        $this->assertTrue($this->symbols->isBuiltInConstant('PHP_INT_MAX'));
    }

    #[Test]
    public function it_does_not_recognize_unknown_constants(): void
    {
        $this->assertFalse($this->symbols->isBuiltInConstant('MY_CUSTOM_CONSTANT'));
    }

    #[Test]
    public function is_built_in_type_covers_all_type_categories(): void
    {
        // Class
        $this->assertTrue($this->symbols->isBuiltInType('stdClass'));
        // Interface
        $this->assertTrue($this->symbols->isBuiltInType('Iterator'));
        // Not built-in
        $this->assertFalse($this->symbols->isBuiltInType('My_Custom_Class'));
    }

    #[Test]
    public function it_recognizes_built_in_class_with_different_casing(): void
    {
        $this->assertTrue($this->symbols->isBuiltInClass('stdclass'));
        $this->assertTrue($this->symbols->isBuiltInClass('STDCLASS'));
        $this->assertTrue($this->symbols->isBuiltInClass('exception'));
    }

    #[Test]
    public function it_recognizes_built_in_function_with_different_casing(): void
    {
        $this->assertTrue($this->symbols->isBuiltInFunction('STRLEN'));
        $this->assertTrue($this->symbols->isBuiltInFunction('Array_Map'));
        $this->assertTrue($this->symbols->isBuiltInFunction('CLASS_EXISTS'));
    }

    #[Test]
    public function it_does_not_recognize_constant_with_different_casing(): void
    {
        // Constants are case-sensitive
        $this->assertFalse($this->symbols->isBuiltInConstant('php_eol'));
        $this->assertFalse($this->symbols->isBuiltInConstant('php_int_max'));
    }

    #[Test]
    public function it_accepts_custom_data_file_path(): void
    {
        $customFile = __DIR__ . '/fixtures/custom-symbols.php';

        if (!is_dir(__DIR__ . '/fixtures')) {
            mkdir(__DIR__ . '/fixtures');
        }

        file_put_contents($customFile, "<?php\nreturn [\n" .
            "    'classes' => ['CustomClass' => true],\n" .
            "    'interfaces' => [],\n" .
            "    'traits' => [],\n" .
            "    'enums' => [],\n" .
            "    'functions' => [],\n" .
            "    'constants' => [],\n" .
            "];\n");

        $symbols = new BuiltInSymbols($customFile);

        $this->assertTrue($symbols->isBuiltInClass('CustomClass'));
        $this->assertFalse($symbols->isBuiltInClass('stdClass'));

        unlink($customFile);
        rmdir(__DIR__ . '/fixtures');
    }
}
