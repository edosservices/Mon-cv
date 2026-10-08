<?php

$source = __DIR__;
$output = $source . DIRECTORY_SEPARATOR . 'limete-wifi-hostinger-correct-v2.zip';

if (file_exists($output)) {
    unlink($output);
}

$zip = new ZipArchive();

if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    exit("ERREUR : impossible de créer le ZIP.\n");
}

$excluded = [
    '.git',
    'node_modules',
    '.env',
    'storage/app/hostinger',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $source,
        FilesystemIterator::SKIP_DOTS
    ),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    $fullPath = $file->getPathname();

    $relativePath = substr(
        $fullPath,
        strlen($source) + 1
    );

    // IMPORTANT : toujours des "/" dans le ZIP
    $relativePath = str_replace('\\', '/', $relativePath);

    if ($relativePath === 'make-hostinger-zip.php') {
        continue;
    }

    $skip = false;

    foreach ($excluded as $item) {
        if (
            $relativePath === $item ||
            str_starts_with($relativePath, $item . '/')
        ) {
            $skip = true;
            break;
        }
    }

    if ($skip) {
        continue;
    }

    if ($file->isDir()) {
        $zip->addEmptyDir($relativePath);
    } else {
        $zip->addFile($fullPath, $relativePath);
    }
}

$zip->close();

echo "ZIP créé avec succès :\n";
echo $output . "\n";