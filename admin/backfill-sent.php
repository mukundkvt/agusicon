<?php
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403); exit('Forbidden');
}

$csvPath   = dirname(__DIR__) . '/data/training-enrollments.csv';
$trackPath = dirname(__DIR__) . '/data/training-sent.json';
$now       = date('Y-m-d H:i:s');

if (!is_file($csvPath)) {
    exit('CSV not found: ' . $csvPath);
}

// Read CSV
$handle = fopen($csvPath, 'r');
$headers = fgetcsv($handle);
$emailIdx = array_search('Email', $headers);
$subAtIdx = array_search('Submitted At', $headers);

if ($emailIdx === false || $subAtIdx === false) {
    fclose($handle);
    exit('Could not find Email or Submitted At columns. Headers: ' . implode(', ', $headers));
}

$keys = [];
while (($row = fgetcsv($handle)) !== false) {
    $email = $row[$emailIdx] ?? '';
    $subAt = $row[$subAtIdx] ?? '';
    if ($email === '') continue;
    $key = md5($email . $subAt);
    $keys[$key] = $now;
}
fclose($handle);

// Merge with existing
$existing = [];
if (is_file($trackPath)) {
    $existing = json_decode(file_get_contents($trackPath), true) ?: [];
}
// Existing entries keep their original timestamp; only add new ones
$merged = array_merge($keys, $existing);

$fh = fopen($trackPath, 'w');
if ($fh && flock($fh, LOCK_EX)) {
    fwrite($fh, json_encode($merged, JSON_PRETTY_PRINT));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
} else {
    exit('Could not write to ' . $trackPath);
}

$count = count($keys);
echo "Done. Marked {$count} entr" . ($count === 1 ? 'y' : 'ies') . " as Sent (timestamp: {$now}).\n";
echo "You can delete this file now: admin/backfill-sent.php";
