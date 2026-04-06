<?php
function fix_file($filepath) {
    if (!file_exists($filepath)) return;
    $content = file_get_contents($filepath);
    
    // Try the cp1252 to utf8 mapping reverse conversion using iconv
    // Since Windows-1252 encoding string might be slightly off sometimes,
    // we'll do the explicit string replacement.
    $replacements = [
        "ÃƒÂ©" => "é",
        "ÃƒÂ " => "à ",
        "ÃƒÂ¨" => "è",
        "ÃƒÂª" => "ê",
        "ÃƒÂ®" => "î",
        "ÃƒÂ´" => "ô",
        "ÃƒÂ»" => "û",
        "Ã¢Â Â¤Ã¯Â¸Â" => "❤️",
        "Ã¢Â Â¤Ã¯Â¸Â " => "❤️ ",
        "Ã¢â‚¬â€ " => "—",
        "Ãƒâ€°" => "É",
        "Ãƒâ‚¬" => "À",
        "Ã°Å¸Å’Â¾" => "🌾",
        "Ã°Å¸â€œÅ¡" => "📚",
        "Ã°Å¸Å½Â¤" => "🎤",
        "Ã°Å¸â€ Â§" => "🔧",
        "Ã¢Â Â¤" => "❤️",
        "ÃƒÂ§" => "ç",
        "Ã¢â‚¬â„¢" => "'",
    ];
    
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    // Also fix the case with space "ÃƒÂ " means à with space or something similar
    // Let's do regex to find any remaining ÃƒÂ followed by a whitespace 
    // wait, "ÃƒÂ " with a non breaking space maybe?
    // Let's just str_replace "ÃƒÂ\xA0" if it's there
    $content = str_replace("ÃƒÂ\xA0", "à", $content);
    $content = str_replace("ÃƒÂ ", "à", $content); // In case it's verbatim 
    
    file_put_contents($filepath, $content);
    echo "Fixed: $filepath\n";
}

fix_file("templates/Evenement/inscriptions.html.twig");
fix_file("templates/Evenement/favoris.html.twig");
echo "Done.\n";
