<?php
$filepath = "c:/Users/hp/ArdhiWEB/templates/Evenement/inscriptions.html.twig";
$content = file_get_contents($filepath);
$content = str_replace("inscription(s) Ã¢â‚¬â€  consultez le statut de chacune.", "inscription(s) — consultez le statut de chacune.", $content);
file_put_contents($filepath, $content);
echo "Fixed!";
