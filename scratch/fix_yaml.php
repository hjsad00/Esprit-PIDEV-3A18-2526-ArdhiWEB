<?php

$yamlFiles = [
    'ar' => 'c:/Users/MSI/ArdhiWEB/translations/messages.ar.yaml',
    'fr' => 'c:/Users/MSI/ArdhiWEB/translations/messages.fr.yaml',
    'en' => 'c:/Users/MSI/ArdhiWEB/translations/messages.en.yaml'
];

foreach ($yamlFiles as $lang => $file) {
    if (!file_exists($file)) continue;
    
    $content = file_get_contents($file);
    
    // We appended a second "compare:" and "reco:" under chatbot block. Let's fix that.
    // The previous block was around:
    //     reco:
    //         need_task: ...
    //         analysis_done: ...
    //         no_match: ...
    //     compare:
    //         need_task: ...
    
    // I can just replace the first `    reco:\n` with the new keys inserted before `need_task`.
    // Wait, let's just use Symfony Yaml component, it's MUCH safer!
    require_once 'c:/Users/MSI/ArdhiWEB/vendor/autoload.php';
    
    $parsed = \Symfony\Component\Yaml\Yaml::parse($content);
    
    // The file is corrupted now due to duplicates, parse might fail!
    // So let's first fix the file manually or restore it and merge.
}
