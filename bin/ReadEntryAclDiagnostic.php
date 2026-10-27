<?php declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$path = sys_get_temp_dir() . '/base3-xrm-entry-acl-'
	. substr(hash('sha256', dirname(__DIR__)), 0, 12) . '.json';

if (($argv[1] ?? '') === '--clear') {
	if (is_file($path) && !@unlink($path)) {
		fwrite(STDERR, "Could not delete diagnostic file: $path\n");
		exit(1);
	}
	echo "Diagnostic cleared\n";
	exit;
}

if (!is_file($path)) {
	fwrite(STDERR, "No diagnostic file at $path\nCheck that the XRM HTTP PHP process can write there and shares the same temporary directory.\n");
	exit(1);
}

$records = json_decode((string) file_get_contents($path), true);
if (!is_array($records)) {
	fwrite(STDERR, "Diagnostic file is invalid\n");
	exit(1);
}

echo json_encode(array_slice($records, -10), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
