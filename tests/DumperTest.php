<?php declare(strict_types=1);

namespace Tests\Glider88;

use Glider88\Dumper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DumperTest extends TestCase
{
    public static function dataProvider(): array
    {
        $arr = <<<PHP
[
  'a' => 'b',
  0 => [],
  'b' => 'c',
]
PHP;
        $obj = <<<PHP
Dumper::object(
  'Tests\Glider88\A1',
  [
    'a2' => 2,
    'b2' => 'a2',
    'c2' => false,
    'd2' => Dumper::object(
      'Tests\Glider88\B',
      [
        'a3' => ['h', 'w'],
        'b3' => 'b',
        'c3' => true,
      ]
    ),
    'a1' => 1,
    'b1' => 'a1',
    'c1' => true,
  ]
)
PHP;

        return [
            'int' => [7, '7'],
            'string' => ['this str', "'this str'"],
            'empty string' => ['', "''"],
            'null' => [null, 'null'],
            'bool' => [true, 'true'],
            'flat array' => [[1, 's', null, false], "[1, 's', null, false]"],
            'array' => [['a' => 'b', [], 'b' => 'c'], $arr],
            'object' => [new A1(), $obj],
            'remove' => [],
        ];
    }

    #[DataProvider('dataProvider')]
    public function testDumper(mixed $value, mixed $expected): void
    {
        $this->assertSame($expected, Dumper::val($value));
    }
}

class B {function __construct(private array $a3, protected ?string $b3, public bool $c3) {}}

class A2 {function __construct(private int $a2, protected string $b2, public bool $c2, private B $d2) {}}

class A1 extends A2
{
    function __construct(private int $a1 = 1, protected string $b1 = 'a1', public bool $c1 = true)
    {
        parent::__construct(2, 'a2', false, new B(['h', 'w'], 'b', true));
    }
}
