[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [string] $ArchivePath,

    [string] $ExpectedVersion,

    [switch] $SkipFilenameCheck
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$resolvedArchivePath = (Resolve-Path -LiteralPath $ArchivePath).Path
$expectedRoot = 'am-toolkit/'
$expectedMainFile = 'am-toolkit/am-toolkit.php'

$zip = [IO.Compression.ZipFile]::OpenRead($resolvedArchivePath)

try {
    $entries = @($zip.Entries)

    if ($entries.Count -eq 0) {
        throw 'Paczka ZIP jest pusta.'
    }

    if ($entries[0].FullName -ne $expectedMainFile) {
        throw (
            "The first ZIP entry must be $expectedMainFile, " +
            "found: $($entries[0].FullName)"
        )
    }

    $directoryEntries = @(
        $entries | Where-Object { $_.FullName.EndsWith('/') }
    )

    if ($directoryEntries.Count -gt 0) {
        throw 'Directory entries are not allowed. The v0.9.0 reference contains file entries only.'
    }

    $backslashEntries = @(
        $entries | Where-Object { $_.FullName.Contains('\') }
    )

    if ($backslashEntries.Count -gt 0) {
        throw 'ZIP paths must use "/" instead of "\".'
    }

    $outsideRoot = @(
        $entries |
            Where-Object { -not $_.FullName.StartsWith($expectedRoot) }
    )

    if ($outsideRoot.Count -gt 0) {
        throw 'Every file must be located inside the single am-toolkit/ root.'
    }

    $mainFiles = @(
        $entries | Where-Object { $_.FullName -eq $expectedMainFile }
    )

    if ($mainFiles.Count -ne 1) {
        throw 'The release must contain exactly one am-toolkit/am-toolkit.php file.'
    }

    if ($null -eq $zip.GetEntry('am-toolkit/vendor/autoload.php')) {
        throw 'The release is missing the production Composer autoloader.'
    }

    if (
        $null -ne $zip.GetEntry('am-toolkit/composer.json') -or
        $null -ne $zip.GetEntry('am-toolkit/composer.lock')
    ) {
        throw 'Composer metadata must not be shipped in the production ZIP.'
    }

    $duplicates = @(
        $entries |
            Group-Object FullName |
            Where-Object { $_.Count -gt 1 }
    )

    if ($duplicates.Count -gt 0) {
        throw 'Duplicate ZIP paths detected.'
    }

    $mainFileEntry = $zip.GetEntry($expectedMainFile)
    $reader = [IO.StreamReader]::new($mainFileEntry.Open())

    try {
        $mainFileContents = $reader.ReadToEnd()
    } finally {
        $reader.Dispose()
    }

    if ($mainFileContents -notmatch '(?m)^\s*\*\s*Plugin Name:\s*AM Toolkit\s*$') {
        throw 'The main file is missing the Plugin Name: AM Toolkit header.'
    }

    if ($mainFileContents -notmatch "require_once\s+AM_TOOLKIT_PATH\s*\.\s*'vendor/autoload\.php'") {
        throw 'The production bootstrap does not load vendor/autoload.php.'
    }

    $versionMatch = [regex]::Match(
        $mainFileContents,
        '(?m)^\s*\*\s*Version:\s*([^\r\n]+)\s*$'
    )

    if (-not $versionMatch.Success) {
        throw 'The main file is missing the Version header.'
    }

    $packageVersion = $versionMatch.Groups[1].Value.Trim()

    if (
        -not [string]::IsNullOrWhiteSpace($ExpectedVersion) -and
        $packageVersion -ne $ExpectedVersion
    ) {
        throw (
            "Package version ($packageVersion) does not match " +
            "the expected version ($ExpectedVersion)."
        )
    }

    if (-not $SkipFilenameCheck) {
        $expectedFilename = "am-toolkit-v$packageVersion.zip"
        $actualFilename = [IO.Path]::GetFileName($resolvedArchivePath)

        if ($actualFilename -ne $expectedFilename) {
            throw (
                "The filename must be $expectedFilename, " +
                "found: $actualFilename"
            )
        }
    }

    Write-Host (
        "OK: {0} | version {1} | {2} files | internal root {3}" -f
        [IO.Path]::GetFileName($resolvedArchivePath),
        $packageVersion,
        $entries.Count,
        $expectedRoot
    ) -ForegroundColor Green
} finally {
    $zip.Dispose()
}
