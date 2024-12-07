Dump php values as valid php values:

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
