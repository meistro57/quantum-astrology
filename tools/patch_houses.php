<?php
declare(strict_types=1);

$file = __DIR__ . '/../classes/Charts/SwissEphemeris.php';
$part = __DIR__ . '/houses_method.part';

$orig = file_get_contents($file);
if ($orig === false) { fwrite(STDERR, "Cannot read $file\n"); exit(1); }

$newMethod = file_get_contents($part);
if ($newMethod === false || trim($newMethod) === '') { fwrite(STDERR, "Cannot read $part\n"); exit(2); }

$start = strpos($orig, 'public static function houses');
if ($start === false) { fwrite(STDERR, "houses() not found in SwissEphemeris.php\n"); exit(3); }

$bracePos = strpos($orig, '{', $start);
if ($bracePos === false) { fwrite(STDERR, "Opening brace not found\n"); exit(4); }

$depth = 0;
$end = null;
$len = strlen($orig);
for ($i = $bracePos; $i < $len; $i++) {
    $ch = $orig[$i];
    if ($ch === '{') $depth++;
    if ($ch === '}') $depth--;
    if ($depth === 0) { $end = $i; break; }
}
if ($end === null) { fwrite(STDERR, "Matching closing brace not found\n"); exit(5); }

// Splice: replace from the function keyword to the closing brace (inclusive)
$patched = substr($orig, 0, $start) . $newMethod . substr($orig, $end + 1);

copy($file, $file . '.prepatch.bak');
if (file_put_contents($file, $patched) === false) { fwrite(STDERR, "Write failed\n"); exit(6); }

echo "Patched houses() successfully.\n";
