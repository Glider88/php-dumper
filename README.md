# PHP Dumper
Dump php variable as valid php code.

Installation:
```shell
composer require glider88/php-dumper
```

Start:
```shell
bin/re  # first run
```
```shell
bin/up  # start app
```
```shell
bin/unit # run tests
```

Values:
```php 
[1, 's', null, false];

[['a' => 'b', [], 'b' => 'c'];

new SomeClass(prop1: 'one', prop2: 12);
```
Dump to:
```php
"[1, 's', null, false]";

"[
  'a' => 'b',
  0 => [],
  'b' => 'c',
]";

"Dumper::object(
  'SomeClass',
  [
    'prop1' => 'one',
    'prop2' => 12,
  ]
)";
```
Limitations:
- resources are replaced as 'RESOURCE'
- Closures as PhpDumper::void()

For recursive objects or complex data use:
```php
PhpDumper::dump()
```
This dump saves data to a file, and once the file is required, you can get data in variable `$result_`

It is possible to add your own data hooks:
```php
$hooks = [
    [
        static fn($var) => $var instanceof LaravelModel, 
        static fn(LaravelModel $m) => '\\' . $m::class . "::find($m->id)",
    ],
];

PhpDumper::dd([1, LaravelModel::find(1), true], $hooks);
```
