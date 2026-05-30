<?php
/**
 * Cross-platform PHP lint runner for the plugin source.
 *
 * Usage: php tests/bin/php-lint.php
 */

$files = [];
$dirs = ['includes', 'disable-emails-per-product-for-woocommerce.php'];

foreach ($dirs as $dir) {
	if (is_file($dir)) {
		$files[] = $dir;
		continue;
	}
	if (!is_dir($dir)) {
		continue;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
	);
	foreach ($iterator as $file) {
		if ($file->isFile() && $file->getExtension() === 'php') {
			$files[] = $file->getPathname();
		}
	}
}

$failed = false;
foreach ($files as $file) {
	$output = [];
	$exit = 0;
	exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $exit);
	if ($exit !== 0) {
		echo implode("\n", $output) . "\n";
		$failed = true;
	}
}

if (!$failed) {
	echo "No syntax errors detected in " . count($files) . " files.\n";
}

exit($failed ? 1 : 0);
