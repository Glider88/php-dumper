<?php declare(strict_types=1);

namespace Tests\Glider88;

use Glider88\PhpDumper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhpDumperTest extends TestCase
{
    public static function dataProvider(): array
    {
        $a = new A();
        $b = new B();
        $c = new C();
        $a->arr = [$b, $c];

        return [
            'int' => [7],
            'string' => ['this str'],
            'empty string' => [''],
            'null' => [null],
            'bool' => [true],
            'flat array' => [[1, 's', null, false]],
            'array' => [['a' => 'b', [], 'b' => 'c']],
            'object' => [$c],
            'nesting' => [$a],
        ];
    }

    #[DataProvider('dataProvider')]
    public function testDumper(mixed $expected): void
    {
        $use = 'use Glider88\PhpDumper;';
        eval($use . '$actual = ' . PhpDumper::val($expected));
        $this->assertSame(serialize($expected), serialize($actual));
    }
}

class A
{
    public $name = 'A';
    public $arr = [];
    public $bool = true;
}

class B
{
    public $arr = [];
}

class C
{
    public $name = 'C';
}
