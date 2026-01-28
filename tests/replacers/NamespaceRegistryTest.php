<?php

declare(strict_types=1);

use CoenJacobs\Mozart\Replace\NamespaceRegistry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class NamespaceRegistryTest extends TestCase
{
    protected NamespaceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new NamespaceRegistry();
    }

    #[Test]
    public function it_can_register_a_namespace(): void
    {
        $this->registry->register('Invoker', 'MyPlugin\\Dependencies\\Invoker');

        $this->assertTrue($this->registry->hasNamespace('Invoker'));
        $this->assertEquals(
            'MyPlugin\\Dependencies\\Invoker',
            $this->registry->getPrefixedNamespace('Invoker')
        );
    }

    #[Test]
    public function it_normalizes_leading_backslashes(): void
    {
        $this->registry->register('\\Invoker', '\\MyPlugin\\Dependencies\\Invoker');

        $this->assertTrue($this->registry->hasNamespace('Invoker'));
        $this->assertTrue($this->registry->hasNamespace('\\Invoker'));
        $this->assertEquals(
            'MyPlugin\\Dependencies\\Invoker',
            $this->registry->getPrefixedNamespace('Invoker')
        );
    }

    #[Test]
    public function it_normalizes_trailing_backslashes(): void
    {
        $this->registry->register('Invoker\\', 'MyPlugin\\Dependencies\\Invoker\\');

        $this->assertTrue($this->registry->hasNamespace('Invoker'));
        $this->assertEquals(
            'MyPlugin\\Dependencies\\Invoker',
            $this->registry->getPrefixedNamespace('Invoker')
        );
    }

    #[Test]
    public function it_returns_null_for_unregistered_namespace(): void
    {
        $this->assertNull($this->registry->getPrefixedNamespace('Unknown'));
    }

    #[Test]
    public function it_returns_false_for_unregistered_namespace_check(): void
    {
        $this->assertFalse($this->registry->hasNamespace('Unknown'));
    }

    #[Test]
    public function it_can_register_nested_namespaces(): void
    {
        $this->registry->register('Invoker\\Invoker', 'MyPlugin\\Dependencies\\Invoker\\Invoker');

        $this->assertTrue($this->registry->hasNamespace('Invoker\\Invoker'));
        $this->assertEquals(
            'MyPlugin\\Dependencies\\Invoker\\Invoker',
            $this->registry->getPrefixedNamespace('Invoker\\Invoker')
        );
    }

    #[Test]
    public function it_can_get_all_registered_namespaces(): void
    {
        $this->registry->register('Invoker', 'MyPlugin\\Dependencies\\Invoker');
        $this->registry->register('Psr\\Container', 'MyPlugin\\Dependencies\\Psr\\Container');

        $all = $this->registry->getAll();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('Invoker', $all);
        $this->assertArrayHasKey('Psr\\Container', $all);
    }

    #[Test]
    public function it_starts_empty(): void
    {
        $this->assertTrue($this->registry->isEmpty());
    }

    #[Test]
    public function it_is_not_empty_after_registration(): void
    {
        $this->registry->register('Invoker', 'MyPlugin\\Dependencies\\Invoker');

        $this->assertFalse($this->registry->isEmpty());
    }

    #[Test]
    public function it_can_clear_all_namespaces(): void
    {
        $this->registry->register('Invoker', 'MyPlugin\\Dependencies\\Invoker');
        $this->registry->clear();

        $this->assertTrue($this->registry->isEmpty());
        $this->assertFalse($this->registry->hasNamespace('Invoker'));
    }

    #[Test]
    public function it_overwrites_existing_namespace_on_re_register(): void
    {
        $this->registry->register('Invoker', 'MyPlugin\\V1\\Invoker');
        $this->registry->register('Invoker', 'MyPlugin\\V2\\Invoker');

        $this->assertEquals(
            'MyPlugin\\V2\\Invoker',
            $this->registry->getPrefixedNamespace('Invoker')
        );
    }
}
