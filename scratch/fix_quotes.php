<?php

$yamlFiles = [
    'ar' => 'c:/Users/MSI/ArdhiWEB/translations/messages.ar.yaml',
    'fr' => 'c:/Users/MSI/ArdhiWEB/translations/messages.fr.yaml',
    'en' => 'c:/Users/MSI/ArdhiWEB/translations/messages.en.yaml'
];

foreach ($yamlFiles as $lang => $file) {
    if (!file_exists($file)) continue;
    
    $content = file_get_contents($file);
    // Escape internal double quotes inside the already double-quoted strings
    $content = str_replace('"%comp%"', '\\"%comp%\\"', $content);
    $content = str_replace('"%task%"', '\\"%task%\\"', $content);
    
    file_put_contents($file, $content);
}

echo "Quotes escaped successfully.\n";
