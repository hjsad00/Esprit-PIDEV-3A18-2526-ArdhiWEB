<?php

require 'vendor/autoload.php';
foreach (glob('src/Controller/UserAndDiag/Admin/Admin*Controller.php') as $file) {
    preg_match('/use App\\\\Entity\\\\(?:UserAndDiag\\\\)?([a-zA-Z0-9_]+);/', file_get_contents($file), $matches);
    if (!empty($matches[1])) {
        $entity = $matches[1];
        $typeClass = "UserAndDiag/Admin/Admin{$entity}Type";

        echo "Generating {$typeClass} for {$entity}...\n";
        $fqcn = class_exists("App\\Entity\\UserAndDiag\\{$entity}") ? "App\\Entity\\UserAndDiag\\{$entity}" : "App\\Entity\\{$entity}";
        exec("php bin/console make:form \"{$typeClass}\" \"{$fqcn}\" --no-interaction", $output, $returnVar);
        echo implode("\n", $output) . "\n";
    }
}
