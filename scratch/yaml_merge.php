<?php

$yamlFiles = [
    'ar' => 'c:/Users/MSI/ArdhiWEB/translations/messages.ar.yaml',
    'fr' => 'c:/Users/MSI/ArdhiWEB/translations/messages.fr.yaml',
    'en' => 'c:/Users/MSI/ArdhiWEB/translations/messages.en.yaml'
];

$translations = [
    'notification' => [
        'mark_read' => ['ar' => 'تحديد كمقروء', 'fr' => 'Marquer comme lu', 'en' => 'Mark as read'],
    ],
    'time' => [
        'days' => ['ar' => 'يوم (أيام)', 'fr' => 'j', 'en' => 'd'],
        'hours' => ['ar' => 'ساعة (ساعات)', 'fr' => 'h', 'en' => 'h'],
        'mins' => ['ar' => 'دقيقة (دقائق)', 'fr' => 'min', 'en' => 'min'],
        'just_now' => ['ar' => 'الآن', 'fr' => 'À l\'instant', 'en' => 'Just now'],
    ],
    'chatbot' => [
        'performance' => [
            'score_line' => ['ar' => 'النتيجة: %score%/100 — %appreciation%', 'fr' => '💼 Score: %score%/100 — %appreciation%', 'en' => '💼 Score: %score%/100 — %appreciation%'],
            'title' => ['ar' => '📊 **تصنيف أفضل الأداء:**', 'fr' => '📊 **Classement des Top Performeurs :**', 'en' => '📊 **Top Performers Ranking:**'],
        ],
        'skills' => [
            'found_title' => ['ar' => '🔍 **%count% موظف (موظفين) بمهارة \\"%comp%\\" :**', 'fr' => '🔍 **%count% employé(s) avec \\"%comp%\\" :**', 'en' => '🔍 **%count% employee(s) with \\"%comp%\\" :**'],
        ],
        'availability' => [
            'title_with_modes' => ['ar' => "📅 **توفر %count% موظف (موظفين) نشطين :**\n🟢 متاح • 🟡 معتدل • 🔴 مثقل", 'fr' => "📅 **Disponibilité des %count% employés actifs :**\n🟢 Disponible • 🟡 Modéré • 🔴 Surchargé", 'en' => "📅 **Availability of %count% active employees:**\n🟢 Available • 🟡 Moderate • 🔴 Overloaded"],
            'no_info' => ['ar' => '📅 لا توجد معلومات عن التوفر.', 'fr' => '📅 Aucune information de disponibilité.', 'en' => '📅 No availability information.'],
        ],
        'compare' => [
            'not_enough_employees' => ['ar' => '😕 أقل من موظفين اثنين متاحين لهذه المهمة.', 'fr' => '😕 Moins de 2 employés disponibles pour cette tâche.', 'en' => '😕 Less than 2 employees available for this task.'],
            'title' => ['ar' => '🏆 **مقارنة أفضل 3 لـ \\"%task%\\"**', 'fr' => '🏆 **Comparaison Top 3 pour \\"%task%\\"**', 'en' => '🏆 **Top 3 Comparison for \\"%task%\\"**'],
        ],
        'reco' => [
            'task_not_found' => ['ar' => '❌ مهمة غير موجودة.', 'fr' => '❌ Tâche introuvable.', 'en' => '❌ Task not found.'],
            'already_assigned_reason' => ['ar' => 'تم تعيينه بالفعل لهذه المهمة.', 'fr' => 'Déjà assigné à cette tâche.', 'en' => 'Already assigned to this task.'],
            'already_assigned' => ['ar' => '✅ موظف (موظفين) تم تعيينهم بالفعل لـ \\"%task%\\"', 'fr' => '✅ Employé(s) déjà assigné(s) à \\"%task%\\"', 'en' => '✅ Employee(s) already assigned to \\"%task%\\"'],
        ],
        'error' => [
            'server_returned_error' => ['ar' => 'عذراً، حدث خطأ: ', 'fr' => 'Désolé, j\'ai rencontré une erreur : ', 'en' => 'Sorry, I encountered an error: '],
            'network' => ['ar' => 'عذراً! لا يمكنني الاتصال بالخادم.', 'fr' => 'Oups ! Je n\'arrive pas à joindre le serveur.', 'en' => 'Oops! I cannot reach the server.'],
        ]
    ]
];

foreach ($yamlFiles as $lang => $file) {
    if (!file_exists($file)) continue;
    
    // We will parse, array_replace_recursive, and dump
    require_once 'c:/Users/MSI/ArdhiWEB/vendor/autoload.php';
    
    $parsed = \Symfony\Component\Yaml\Yaml::parseFile($file);
    
    $parsed['notification']['mark_read'] = $translations['notification']['mark_read'][$lang];
    
    $parsed['time']['days'] = $translations['time']['days'][$lang];
    $parsed['time']['hours'] = $translations['time']['hours'][$lang];
    $parsed['time']['mins'] = $translations['time']['mins'][$lang];
    $parsed['time']['just_now'] = $translations['time']['just_now'][$lang];
    
    if(!isset($parsed['chatbot'])) $parsed['chatbot'] = [];
    
    $parsed['chatbot']['performance']['score_line'] = $translations['chatbot']['performance']['score_line'][$lang];
    $parsed['chatbot']['performance']['title'] = $translations['chatbot']['performance']['title'][$lang];
    
    $parsed['chatbot']['skills']['found_title'] = $translations['chatbot']['skills']['found_title'][$lang];
    
    $parsed['chatbot']['availability']['title_with_modes'] = $translations['chatbot']['availability']['title_with_modes'][$lang];
    $parsed['chatbot']['availability']['no_info'] = $translations['chatbot']['availability']['no_info'][$lang];
    
    $parsed['chatbot']['compare']['not_enough_employees'] = $translations['chatbot']['compare']['not_enough_employees'][$lang];
    $parsed['chatbot']['compare']['title'] = $translations['chatbot']['compare']['title'][$lang];
    
    $parsed['chatbot']['reco']['task_not_found'] = $translations['chatbot']['reco']['task_not_found'][$lang];
    $parsed['chatbot']['reco']['already_assigned_reason'] = $translations['chatbot']['reco']['already_assigned_reason'][$lang];
    $parsed['chatbot']['reco']['already_assigned'] = $translations['chatbot']['reco']['already_assigned'][$lang];
    
    $parsed['chatbot']['error']['server_returned_error'] = $translations['chatbot']['error']['server_returned_error'][$lang];
    $parsed['chatbot']['error']['network'] = $translations['chatbot']['error']['network'][$lang];
    
    $yamlOut = \Symfony\Component\Yaml\Yaml::dump($parsed, 4, 4, \Symfony\Component\Yaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    
    file_put_contents($file, $yamlOut);
}

echo "Files dumped perfectly!\n";
