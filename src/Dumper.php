<?php declare(strict_types=1);

namespace Glider88;

class Dumper {
    public static function d(mixed $data): void
    {
        echo self::php_value($data) . ';' . PHP_EOL;
    }

    public static function dd(mixed $data)
    {
        self::d($data);die;
    }

    public static function val(mixed $data): string
    {
        return self::php_value($data);
    }

    /** @param array<string, mixed> $propertyToValue */
    public static function object(string $classname, array $propertyToValue): object
    {
        $reflector = new \ReflectionClass($classname);
        /** @var object $instance */
        $instance = $reflector->newInstanceWithoutConstructor();
        do {
            foreach ($reflector->getProperties() as $property) {
                $name = $property->getName();
                $value = $propertyToValue[$name];
                $property->setAccessible(true);
                $property->setValue($instance, $value);
            }
        } while ($reflector = $reflector->getParentClass());

        return $instance;
    }

    private static function scalar(mixed $arg): string
    {
        return match (true) {
            $arg === ''     => '\'\'',
            $arg === null   => 'null',
            $arg === true   => 'true',
            $arg === false  => 'false',
            is_string($arg) => "'" . $arg . "'",
            is_scalar($arg) => (string) $arg,
            default => throw new \InvalidArgumentException(
                "try find scalar, but its not scalar: " . var_export($arg, true)
            ),
        };
    }

    private static function is_flat($args): bool
    {
        if ($args === null || is_scalar($args)) {
            return true;
        }

        if (is_array($args)) {
            if (! array_is_list($args)) {
                return false;
            }

            foreach ($args as $arg) {
                if (! self::is_flat($arg)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private static function php_flat_value($args): string
    {
        if (is_array($args)) {
            return '[' . implode(', ', array_map(static fn($arg) => self::php_flat_value($arg), $args)) . ']';
        }

        return self::scalar($args);
    }

    private static function intend(int $depth): string
    {
        return implode(array_fill(0, $depth, '  '));
    }

    private static function php_value(mixed $arg, int $depth = 0): string
    {
        if (is_array($arg)) {
            if (self::is_flat($arg)) {
                return self::php_flat_value($arg);
            }

            $result = '[' . PHP_EOL;
            $next = '  ';
            foreach ($arg as $key => $value) {
                $result .= self::intend($depth) . $next . self::scalar($key) . ' => ' . self::php_value($value,  $depth + 1) . ',' . PHP_EOL;
            }
            $result .= self::intend($depth) . ']';

            return $result;
        }

        if (is_object($arg)) {
            $cls = $arg::class;
            $reflector = new \ReflectionClass($cls);

            $p2v = [];
            do {
                $local = [];
                foreach ($reflector->getProperties() as $property) {
                    $n = $property->getName();
                    $v = $property->getValue($arg);
                    $local[$n] = $v;
                }

                $p2v = array_merge($local, $p2v);
            } while ($reflector = $reflector->getParentClass());

            $result = 'Dumper::object(' . PHP_EOL;
            $result .= self::intend($depth + 1) . "'$cls'" . ',' . PHP_EOL;
            $result .= self::intend($depth + 1) . self::php_value($p2v, $depth + 1);
            $result .= PHP_EOL . self::intend($depth) . ')';

            return $result;
        }

        return self::scalar($arg);
    }
}
