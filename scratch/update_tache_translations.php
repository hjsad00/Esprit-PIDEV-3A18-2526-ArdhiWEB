<?php

$yamlFiles = [
    'ar' => 'c:/Users/MSI/ArdhiWEB/translations/messages.ar.yaml',
    'fr' => 'c:/Users/MSI/ArdhiWEB/translations/messages.fr.yaml',
    'en' => 'c:/Users/MSI/ArdhiWEB/translations/messages.en.yaml'
];

$translations = [
    'tache' => [
        'status' => [
            'en_attente' => ['ar' => 'قيد الانتظار', 'fr' => 'En attente', 'en' => 'Pending'],
            'en_cours'   => ['ar' => 'قيد الإنجاز', 'fr' => 'En cours', 'en' => 'In progress'],
            'termine'    => ['ar' => 'مكتمل', 'fr' => 'Terminé', 'en' => 'Completed'],
            'valide'     => ['ar' => 'صالح', 'fr' => 'Validé', 'en' => 'Validated'],
            'annule'     => ['ar' => 'ملغى', 'fr' => 'Annulé', 'en' => 'Cancelled'],
        ],
        'category' => [
            'plantation'    => ['ar' => 'زراعة', 'fr' => 'Plantation', 'en' => 'Planting'],
            'recolte'       => ['ar' => 'حصاد', 'fr' => 'Récolte', 'en' => 'Harvest'],
            'irrigation'    => ['ar' => 'ري', 'fr' => 'Irrigation', 'en' => 'Irrigation'],
            'fertilisation' => ['ar' => 'تسميد', 'fr' => 'Fertilisation', 'en' => 'Fertilization'],
            'maintenance'   => ['ar' => 'صيانة', 'fr' => 'Maintenance', 'en' => 'Maintenance'],
            'administratif' => ['ar' => 'إداري', 'fr' => 'Administratif', 'en' => 'Administrative'],
            'autre'         => ['ar' => 'أخرى', 'fr' => 'Autre', 'en' => 'Other'],
            'nettoyage'     => ['ar' => 'تنظيف', 'fr' => 'Nettoyage', 'en' => 'Cleaning'],
            'logistique'    => ['ar' => 'لوجستية', 'fr' => 'Logistique', 'en' => 'Logistics'],
            'formation'     => ['ar' => 'تدريب', 'fr' => 'Formation', 'en' => 'Training'],
            'analyse'       => ['ar' => 'تحليل', 'fr' => 'Analyse', 'en' => 'Analysis'],
            'gestion'       => ['ar' => 'إدارة', 'fr' => 'Gestion', 'en' => 'Management'],
            'energie'       => ['ar' => 'طاقة', 'fr' => 'Énergie', 'en' => 'Energy'],
            'planification' => ['ar' => 'تخطيط', 'fr' => 'Planification', 'en' => 'Planning'],
        ],
        'form' => [
            'priority_low'      => ['ar' => 'منخفضة', 'fr' => 'Basse', 'en' => 'Low'],
            'priority_medium'   => ['ar' => 'متوسطة', 'fr' => 'Moyenne', 'en' => 'Medium'],
            'priority_high'     => ['ar' => 'عالية', 'fr' => 'Haute', 'en' => 'High'],
            'priority_critical' => ['ar' => 'حرجة', 'fr' => 'Critique', 'en' => 'Critical'],
        ]
    ]
];

foreach ($yamlFiles as $lang => $file) {
    if (!file_exists($file)) continue;
    
    require_once 'c:/Users/MSI/ArdhiWEB/vendor/autoload.php';
    $parsed = \Symfony\Component\Yaml\Yaml::parseFile($file);
    
    if(!isset($parsed['tache'])) $parsed['tache'] = [];
    if(!isset($parsed['tache']['status'])) $parsed['tache']['status'] = [];
    if(!isset($parsed['tache']['category'])) $parsed['tache']['category'] = [];
    if(!isset($parsed['tache']['form'])) $parsed['tache']['form'] = [];
    
    foreach ($translations['tache']['status'] as $k => $v) {
        $parsed['tache']['status'][$k] = $v[$lang];
    }
    
    foreach ($translations['tache']['category'] as $k => $v) {
        $parsed['tache']['category'][$k] = $v[$lang];
    }
    
    foreach ($translations['tache']['form'] as $k => $v) {
        $parsed['tache']['form'][$k] = $v[$lang];
    }
    
    $yamlOut = \Symfony\Component\Yaml\Yaml::dump($parsed, 4, 4, \Symfony\Component\Yaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    file_put_contents($file, $yamlOut);
}

echo "tache translations injected.\n";
