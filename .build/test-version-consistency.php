<?php

declare(strict_types=1);

$root = dirname(__DIR__);

/**
 * @return non-empty-string
 */
function readProjectFile(string $path): string
{
    $contents = file_get_contents($path);

    if ($contents === false || $contents === '') {
        throw new RuntimeException(sprintf('Nie można odczytać pliku: %s', $path));
    }

    return $contents;
}

/**
 * @return non-empty-string
 */
function matchVersion(string $contents, string $pattern, string $label): string
{
    if (preg_match($pattern, $contents, $matches) !== 1 || trim($matches[1]) === '') {
        throw new RuntimeException(sprintf('Nie znaleziono wersji: %s', $label));
    }

    return trim($matches[1]);
}

$main = readProjectFile($root . '/am-toolkit.php');
$plugin = readProjectFile($root . '/src/Core/Plugin.php');
$readme = readProjectFile($root . '/README.md');
$changelog = readProjectFile($root . '/CHANGELOG.md');

$headerVersion = matchVersion(
    $main,
    '/^\s*\*\s*Version:\s*([^\r\n]+)\s*$/m',
    'nagłówek wtyczki'
);
$constantVersion = matchVersion(
    $main,
    "/define\('AM_TOOLKIT_VERSION',\s*'([^']+)'\)/",
    'AM_TOOLKIT_VERSION'
);
$classVersion = matchVersion(
    $plugin,
    "/public const VERSION = '([^']+)'/",
    'Plugin::VERSION'
);

if ($headerVersion !== $constantVersion || $headerVersion !== $classVersion) {
    throw new RuntimeException(sprintf(
        'Niezgodne wersje: header=%s, constant=%s, class=%s',
        $headerVersion,
        $constantVersion,
        $classVersion
    ));
}

if (preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $headerVersion) !== 1) {
    throw new RuntimeException(sprintf('Nieprawidłowy format wersji: %s', $headerVersion));
}

$releaseVersion = preg_replace('/[-+].*$/', '', $headerVersion);

if (!is_string($releaseVersion) || $releaseVersion === '') {
    throw new RuntimeException('Nie można ustalić bazowej wersji wydania.');
}

if (strpos($readme, $releaseVersion) === false) {
    throw new RuntimeException(sprintf('README nie opisuje linii %s.', $releaseVersion));
}

if (preg_match('/^##\s+' . preg_quote($releaseVersion, '/') . '\b/m', $changelog) !== 1) {
    throw new RuntimeException(sprintf('CHANGELOG nie zawiera sekcji %s.', $releaseVersion));
}

fwrite(STDOUT, sprintf("OK: spójna wersja AM Toolkit %s\n", $headerVersion));
