<?php declare(strict_types=1);

namespace Glider88;

class PhpDumper
{
    public static array $objectIdToResult = [];
    public static array $arrayIdToResult = [];
    public static array $arrayIdToPropertyToId = [];
    public static array $objectIdToPropertyToId = [];
    public static array $arrayIsList = [];
    public static array $tmp = [];
    public static array $hooks = [];

    public static function d(mixed ...$data): void
    {
        $hooks = [];
        $filteredData = [];
        foreach ($data as $i => $datum) {
            $notHook = true;
            if (is_array($datum)) {
                foreach ($datum as $item) {
                    if (is_array($item) && count($item) === 2) {
                        $p = $item[array_key_first($item)];
                        $f = $item[array_key_last($item)];
                        if (is_callable($p) && is_callable($f)) {
                            $hooks[] = $item;
                            $notHook = false;
                        }
                    }
                }
            }

            if ($notHook) {
                $filteredData[$i] = $datum;
            }
        }

        foreach ($filteredData as $datum) {
            echo self::val($datum, hooks: $hooks) . PHP_EOL . PHP_EOL;
        }
    }

    public static function dd(mixed ...$data): never
    {
        self::d(...$data);die;
    }

    public static function dump(mixed $data, string $dir = '', string $filename = '', string $use = '', array $hooks = []): void
    {
        if ($filename === '') {
            $filename = (new \DateTime())->format('H_i_s') . '.php';
        }

        if ($dir === '') {
            $dir = getcwd();
        }

        $content = '<?php' . PHP_EOL . PHP_EOL;
        $content .= $use . PHP_EOL. PHP_EOL;
        $content .= self::val($data, isSimple: false, hooks: $hooks);
        $dir = rtrim($dir, "/");
        $path = $dir . '/' . $filename;

        file_put_contents($path, $content);

        echo "require_once '" . $path . "';" . PHP_EOL;
    }

    public static function val(mixed $data, $isSimple = true, array $hooks = []): string
    {
        try {
            self::$hooks = $hooks;
            $phpVal = self::php_value($data, isSimple: $isSimple);
            if ($isSimple) {
                return $phpVal . ';';
            }

            if (is_int($phpVal)) {
                $phpVal = '$o' . $phpVal;
            }

            $phpVal .= ';' . PHP_EOL;
            $result = '';

            $fixLine = static function (string $content): string {
                $lines = explode("\n", $content);
                $last = $lines[array_key_last($lines)];
                $len = strpos($last, ')') ?: strpos($last, ']') ?: 0;
                $shrinkFn = static fn(string $l) => preg_replace('/\s{' . $len . '}/', '', $l, 1);
                $fixedLines = array_map($shrinkFn, $lines);

                return implode("\n", $fixedLines) . ';' . PHP_EOL . PHP_EOL;
            };

            foreach (self::$arrayIdToResult as $resultContent) {
                $result .= $fixLine($resultContent);
            }

            foreach (self::$objectIdToResult as $resultContent) {
                $result .= $fixLine($resultContent);
            }

            foreach (self::$arrayIdToPropertyToId as $idOut => $propToIdIn) {
                foreach ($propToIdIn as $prop => $idIn) {
                    $sort = '';
                    if (array_key_exists($idOut, self::$arrayIsList)) {
                        $sort = 'ksort($o' . $idOut . ');';
                    }
                    $fixProp = is_int($prop) ? $prop : "'$prop'";
                    $result .= '$o' . $idOut . '[' . $fixProp . '] = $o' . $idIn . ';' . $sort . PHP_EOL;
                }
            }

            foreach (self::$objectIdToPropertyToId as $idOut => $propToIdIn) {
                foreach ($propToIdIn as $prop => $idIn) {
                    $result .= 'PhpDumper::inject($o' . $idOut . ', ' . "'" . $prop . "'" . ', $o' . $idIn . ');' . PHP_EOL;
                }
            }

            $result .= PHP_EOL . '$result_ = ' . $phpVal . PHP_EOL;

            return $result;

        } finally {
            self::$objectIdToResult = [];
            self::$objectIdToPropertyToId = [];
            self::$arrayIdToPropertyToId = [];
            self::$tmp = [];
            self::$arrayIdToResult = [];
            self::$arrayIsList = [];
            self::$hooks = [];
        }
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
                if (array_key_exists($name, $propertyToValue)) {
                    $value = $propertyToValue[$name];
                    if ($value === null && $property->getType()?->allowsNull() === false) {
                        continue;
                    }

                    $property->setValue($instance, $value);
                }
            }
        } while ($reflector = $reflector->getParentClass());

        return $instance;
    }

    public static function inject(object $object, string $property, mixed $value): void
    {
        $cls = $object::class;
        $reflector = new \ReflectionClass($cls);
        do {
            if ($reflector->hasProperty($property)) {
                $reflector->getProperty($property)->setValue($object, $value);
                break;
            }

        } while ($reflector = $reflector->getParentClass());
    }

    public static function void(): \Closure
    {
        return static function () {};
    }

    private static function scalar(mixed $arg): string
    {
        $fixedStr = static function (string $str): string {
            $str = str_replace("'", "\'", $str);
            $last = substr($str, -1);
            if ($last === "\\") {
                $str .= "\\";
            }

            return "'" . $str . "'";
        };

        return match (true) {
            $arg === ''     => "''",
            $arg === null   => 'null',
            $arg === true   => 'true',
            $arg === false  => 'false',
            is_string($arg) => $fixedStr($arg),
            is_scalar($arg) => (string) $arg,
            is_resource($arg) => "'RESOURCE'",
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
            if (!array_is_list($args)) {
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

    private static function has_object($args): bool
    {
        if (is_object($args)) {
            return true;
        }

        if (is_array($args)) {
            foreach ($args as $arg) {
                if (self::has_object($arg)) {
                    return true;
                }
            }

            return false;
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

    private static function php_value(mixed $arg, int $depth = 0, ?int $rootObjectId = null, $isSimple = false): string | int
    {
        foreach (self::$hooks as [$predicateFn, $factoryFn]) {
            if ($predicateFn($arg)) {
                return $factoryFn($arg);
            }
        }

        $isRoot = $rootObjectId === null;
        if (is_array($arg)) {
            if (self::is_flat($arg)) {
                return self::php_flat_value($arg);
            }

            if ($isSimple || !self::has_object($arg)) {
                $result = '[' . PHP_EOL;
                $next = '  ';
                foreach ($arg as $key => $value) {
                    $pv = self::php_value($value,  $depth + 1, isSimple: $isSimple);
                    $result .= self::intend($depth) . $next . self::scalar($key) . ' => ' . $pv . ',' . PHP_EOL;
                }
                $result .= self::intend($depth) . ']';

                return $result;
            }

            // disable gc
            if ($isRoot) {
                $a = new \ArrayObject($arg);
                $id = spl_object_id($a);
                self::$tmp[$id] = $a;
            }

            $result = '[' . PHP_EOL;
            $next = '  ';
            foreach ($arg as $key => $value) {
                $pv = self::php_value($value,  $depth + 1);
                if (is_int($pv)) {
                    if ($isRoot) {
                        if (is_array($value) && array_is_list($value)) {
                            self::$arrayIsList[$pv] = true;
                        }

                        self::$arrayIdToPropertyToId[$id][$key] = $pv;
                    } else {
                        self::$objectIdToPropertyToId[$rootObjectId][$key] = $pv;
                    }

                    continue;
                }

                $result .= self::intend($depth) . $next . self::scalar($key) . ' => ' . $pv . ',' . PHP_EOL;
            }
            $result .= self::intend($depth) . ']';

            if ($isRoot) {
                self::$arrayIdToResult[$id] = '$o' . $id . ' = ' . $result;
                return $id;
            }

            return $result;
        }

        if (is_object($arg)) {
            if ($isSimple) {
                $p2v = self::p2v($arg);
                $cls = $arg::class;
                $result = 'PhpDumper::object(' . PHP_EOL;
                $result .= self::intend($depth + 1) . "'$cls'" . ',' . PHP_EOL;
                $result .= self::intend($depth + 1) . self::php_value($p2v, $depth + 1, isSimple: $isSimple);
                $result .= PHP_EOL . self::intend($depth) . ')';
                if ($arg instanceof \Closure) {
                    $result = 'PhpDumper::void()';
                }

                return $result;
            }

            $id = spl_object_id($arg);
            if (self::already($id)) {
                return $id;
            }

            if ($arg instanceof \Closure) {
                self::$objectIdToResult[$id] = '$o' . $id . ' = PhpDumper::void()';
                return $id;
            }

            $p2v = self::p2v($arg);
            foreach ($p2v as $p => $v) {
                if (is_object($v)) {
                    self::$objectIdToPropertyToId[$id][$p] = self::php_value($v, $depth);
                }
            }

            $p2v = array_filter($p2v, static fn($v) => !is_object($v));
            $cls = $arg::class;
            $result = 'PhpDumper::object(' . PHP_EOL;
            $result .= self::intend($depth + 1) . "'$cls'" . ',' . PHP_EOL;
            $result .= self::intend($depth + 1) . self::php_value($p2v, $depth + 1, $id);
            $result .= PHP_EOL . self::intend($depth) . ')';

            self::$objectIdToResult[$id] = '$o' . $id . ' = ' . $result;

            return $id;
        }

        return self::scalar($arg);
    }

    private static function p2v(object $object): array
    {
        $cls = $object::class;
        $reflector = new \ReflectionClass($cls);

        $p2v = [];
        do {
            $local = [];
            foreach ($reflector->getProperties() as $property) {
                $n = $property->getName();
                if (!$property->isInitialized($object)) {
                    $v = $property->getDefaultValue();
                } else {
                    $v = $property->getValue($object);
                }
                $local[$n] = $v;
            }

            $p2v[] = $local;
        } while ($reflector = $reflector->getParentClass());

        return array_merge(...$p2v);
    }

    private static function already(int $id): bool
    {
        $exist = array_key_exists($id, self::$objectIdToResult);
        if ($exist) {
            return true;
        }

        self::$objectIdToResult[$id] = null;

        return false;
    }
}
