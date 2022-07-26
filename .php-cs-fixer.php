<?php

$finder = PhpCsFixer\Finder::create()
    ->in(["src", "tests"])
;

$config = new PhpCsFixer\Config();
$config->setRules([
    '@PSR12' => true,
])
    ->setLineEnding("\n")
    ->setIndent(str_repeat(' ', 4))
    ->setFinder($finder);

return $config;
