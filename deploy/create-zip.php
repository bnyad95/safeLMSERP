<?php

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php deploy/create-zip.php <source-directory> <output.zip>\n");
    exit(1);
}

$source = realpath($argv[1]);
$destination = $argv[2];

if ($source === false || ! is_dir($source)) {
    fwrite(STDERR, "The source directory does not exist.\n");
    exit(1);
}

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The PHP zip extension is required to build the package.\n");
    exit(1);
}

$zip = new ZipArchive();
$result = $zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE);

if ($result !== true) {
    fwrite(STDERR, "Could not create ZIP archive (error {$result}).\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $item) {
    if (! $item->isFile()) {
        continue;
    }

    $path = $item->getPathname();
    $relativePath = str_replace('\\', '/', substr($path, strlen($source) + 1));

    if (! $zip->addFile($path, $relativePath)) {
        $zip->close();
        fwrite(STDERR, "Could not add {$relativePath} to the ZIP archive.\n");
        exit(1);
    }

    $zip->setCompressionName($relativePath, ZipArchive::CM_STORE);
}

if (! $zip->close()) {
    fwrite(STDERR, "Could not finish the ZIP archive.\n");
    exit(1);
}

echo "Created {$destination}\n";
