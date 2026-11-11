<?php
$html = file_get_contents("https://geo.dailymotion.com/player/xtv3w.html?video=k1j4YGeru1KuTDBRkrs");
preg_match('/<style id="dmp-critical">(.*?)<\/style>/is', $html, $m);
$css = $m[1] ?? '';
preg_match_all('/(\.[a-zA-Z0-9_\-]+)\s*\{/i', $css, $classes);
$uniqueClasses = array_unique($classes[1]);
sort($uniqueClasses);
print_r($uniqueClasses);
