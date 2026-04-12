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
            'found_title' => ['ar' => '🔍 **%count% موظف (موظفين) بمهارة "%comp%" :**', 'fr' => '🔍 **%count% employé(s) avec "%comp%" :**', 'en' => '🔍 **%count% employee(s) with "%comp%" :**'],
        ],
        'availability' => [
            'title_with_modes' => ['ar' => '📅 **توفر %count% موظف (موظفين) نشطين :**\n🟢 متاح • 🟡 معتدل • 🔴 مثقل', 'fr' => '📅 **Disponibilité des %count% employés actifs :**\n🟢 Disponible • 🟡 Modéré • 🔴 Surchargé', 'en' => '📅 **Availability of %count% active employees:**\n🟢 Available • 🟡 Moderate • 🔴 Overloaded'],
            'no_info' => ['ar' => '📅 لا توجد معلومات عن التوفر.', 'fr' => '📅 Aucune information de disponibilité.', 'en' => '📅 No availability information.'],
        ],
        'compare' => [
            'not_enough_employees' => ['ar' => '😕 أقل من موظفين اثنين متاحين لهذه المهمة.', 'fr' => '😕 Moins de 2 employés disponibles pour cette tâche.', 'en' => '😕 Less than 2 employees available for this task.'],
            'title' => ['ar' => '🏆 **مقارنة أفضل 3 لـ "%task%"**', 'fr' => '🏆 **Comparaison Top 3 pour "%task%"**', 'en' => '🏆 **Top 3 Comparison for "%task%"**'],
        ],
        'reco' => [
            'task_not_found' => ['ar' => '❌ مهمة غير موجودة.', 'fr' => '❌ Tâche introuvable.', 'en' => '❌ Task not found.'],
            'already_assigned_reason' => ['ar' => 'تم تعيينه بالفعل لهذه المهمة.', 'fr' => 'Déjà assigné à cette tâche.', 'en' => 'Already assigned to this task.'],
            'already_assigned' => ['ar' => '✅ موظف (موظفين) تم تعيينهم بالفعل لـ "%task%"', 'fr' => '✅ Employé(s) déjà assigné(s) à "%task%"', 'en' => '✅ Employee(s) already assigned to "%task%"'],
        ],
        'error' => [
            'server_returned_error' => ['ar' => 'عذراً، حدث خطأ: ', 'fr' => 'Désolé, j\'ai rencontré une erreur : ', 'en' => 'Sorry, I encountered an error: '],
            'network' => ['ar' => 'عذراً! لا يمكنني الاتصال بالخادم.', 'fr' => 'Oups ! Je n\'arrive pas à joindre le serveur.', 'en' => 'Oops! I cannot reach the server.'],
        ]
    ]
];

foreach ($yamlFiles as $lang => $file) {
    if (!file_exists($file)) continue;
    
    $content = file_get_contents($file);
    
    // Check missing blocks and append
    if (strpos($content, 'notification:') === false) {
        $content .= "\nnotification:\n";
    }
    if (strpos($content, 'mark_read:') === false) {
        $content = str_replace("notification:\n", "notification:\n    mark_read: \"" . $translations['notification']['mark_read'][$lang] . "\"\n", $content);
    }
    
    // Instead of parsing perfectly, just append missing ones to the end
    // To avoid duplication, let's append only if missing
    
    if (strpos($content, "\ntime:") === false) {
        $content .= "\ntime:\n";
        $content .= "    days: \"" . $translations['time']['days'][$lang] . "\"\n";
        $content .= "    hours: \"" . $translations['time']['hours'][$lang] . "\"\n";
        $content .= "    mins: \"" . $translations['time']['mins'][$lang] . "\"\n";
        $content .= "    just_now: \"" . $translations['time']['just_now'][$lang] . "\"\n";
    }

    if (strpos($content, 'score_line:') === false) {
        $add = "";
        $add .= "    performance:\n";
        $add .= "        score_line: \"" . $translations['chatbot']['performance']['score_line'][$lang] . "\"\n";
        $add .= "        title: \"" . $translations['chatbot']['performance']['title'][$lang] . "\"\n";
        
        $add .= "    skills:\n";
        $add .= "        found_title: \"" . $translations['chatbot']['skills']['found_title'][$lang] . "\"\n";
        
        $add .= "    availability:\n";
        $add .= "        title_with_modes: \"" . str_replace('\n', "\n            ", $translations['chatbot']['availability']['title_with_modes'][$lang]) . "\"\n";
        $add .= "        no_info: \"" . $translations['chatbot']['availability']['no_info'][$lang] . "\"\n";
        
        $add .= "    compare:\n";
        $add .= "        not_enough_employees: \"" . $translations['chatbot']['compare']['not_enough_employees'][$lang] . "\"\n";
        $add .= "        title: \"" . $translations['chatbot']['compare']['title'][$lang] . "\"\n";

        $add .= "    reco:\n";
        $add .= "        task_not_found: \"" . $translations['chatbot']['reco']['task_not_found'][$lang] . "\"\n";
        $add .= "        already_assigned_reason: \"" . $translations['chatbot']['reco']['already_assigned_reason'][$lang] . "\"\n";
        $add .= "        already_assigned: \"" . $translations['chatbot']['reco']['already_assigned'][$lang] . "\"\n";

        $add .= "    error:\n";
        $add .= "        server_returned_error: \"" . $translations['chatbot']['error']['server_returned_error'][$lang] . "\"\n";
        $add .= "        network: \"" . $translations['chatbot']['error']['network'][$lang] . "\"\n";
        
        // Append inside chatbot block
        if (strpos($content, 'chatbot:') !== false) {
            $content = str_replace("chatbot:\n", "chatbot:\n" . $add, $content);
        } else {
            $content .= "\nchatbot:\n" . $add;
        }
    }
    
    file_put_contents($file, $content);
}

echo "Translations appended successfully.\n";
