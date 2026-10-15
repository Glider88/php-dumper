<?php declare(strict_types=1);

require_once 'vendor/autoload.php';

enum Gender {
    case Male;
    case Female;
}

$a = Gender::Male;

\Glider88\PhpDumper::dd($a);


