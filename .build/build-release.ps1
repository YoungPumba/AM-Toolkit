[CmdletBinding()]
param(
    [string] $SourceDirectory,
    [string] $OutputDirectory,
    [switch] $Force
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$pluginRootName = 'am-toolkit'

if ([string]::IsNullOrWhiteSpace($SourceDirectory)) {
    $SourceDirectory = Split-Path -Parent $PSScriptRoot
}

if ([string]::IsNullOrWhiteSpace($OutputDirectory)) {
    $OutputDirectory = Join-Path $SourceDirectory 'dist'
}

$sourcePath = (Resolve-Path -LiteralPath $SourceDirectory).Path.TrimEnd('\', '/')
$mainPluginPath = Join-Path $sourcePath 'am-toolkit.php'

if (-not (Test-Path -LiteralPath $mainPluginPath -PathType Leaf)) {
    throw "Main plugin file not found: $mainPluginPath"
}

& (Join-Path $PSScriptRoot 'validate-source.ps1')

$mainPluginContents = Get-Content -LiteralPath $mainPluginPath -Raw
$versionMatch = [regex]::Match(
    $mainPluginContents,
    '(?m)^\s*\*\s*Version:\s*([^\r\n]+)\s*$'
)

if (-not $versionMatch.Success) {
    throw 'Version header not found in am-toolkit.php.'
}

$version = $versionMatch.Groups[1].Value.Trim()

if ($version -notmatch '^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$') {
    throw "Invalid version in am-toolkit.php: $version"
}

$outputPath = [IO.Path]::GetFullPath($OutputDirectory)
[IO.Directory]::CreateDirectory($outputPath) | Out-Null

$archiveName = "am-toolkit-v$version.zip"
$archivePath = Join-Path $outputPath $archiveName

if ((Test-Path -LiteralPath $archivePath) -and -not $Force) {
    throw "Release already exists: $archivePath. Use -Force to replace it."
}

$temporaryArchivePath = Join-Path $outputPath (
    ".am-toolkit-build-$version-$PID.zip"
)
$stagingContainer = Join-Path ([IO.Path]::GetTempPath()) (
    "am-toolkit-release-$version-$PID"
)
$stagingPath = Join-Path $stagingContainer $pluginRootName

function Get-SourceRelativePath {
    param(
        [Parameter(Mandatory)]
        [string] $FullName
    )

    return $FullName.Substring($sourcePath.Length).TrimStart('\', '/')
}

function Test-IsBuildFile {
    param(
        [Parameter(Mandatory)]
        [string] $RelativePath
    )

    $normalized = $RelativePath.Replace('\', '/')

    return (
        $normalized.StartsWith('.build/') -or
        $normalized.StartsWith('.git/') -or
        $normalized.StartsWith('.vscode/') -or
        $normalized.StartsWith('docs/') -or
        $normalized.StartsWith('vendor/') -or
        $normalized.StartsWith('dist/') -or
        $normalized.StartsWith('.build-output/') -or
        $normalized -eq '.gitignore' -or
        $normalized -eq 'composer.json' -or
        $normalized -eq 'composer.lock' -or
        $normalized -eq 'phpcs.xml.dist' -or
        $normalized -eq '.DS_Store' -or
        $normalized.EndsWith('/.DS_Store') -or
        $normalized -eq 'Thumbs.db' -or
        $normalized.EndsWith('/Thumbs.db')
    )
}

function Add-ZipFile {
    param(
        [Parameter(Mandatory)]
        [System.IO.Compression.ZipArchive] $Archive,

        [Parameter(Mandatory)]
        [System.IO.FileInfo] $File,

        [Parameter(Mandatory)]
        [string] $EntryName
    )

    $entry = $Archive.CreateEntry(
        $EntryName,
        [System.IO.Compression.CompressionLevel]::Optimal
    )

    $entry.LastWriteTime = $File.LastWriteTime

    $inputStream = $File.OpenRead()
    $outputStream = $entry.Open()

    try {
        $inputStream.CopyTo($outputStream)
    } finally {
        $outputStream.Dispose()
        $inputStream.Dispose()
    }
}

try {
    [IO.Directory]::CreateDirectory($stagingPath) | Out-Null

    $sourceFiles = Get-ChildItem -LiteralPath $sourcePath -Recurse -File |
        Where-Object {
            -not (Test-IsBuildFile -RelativePath (
                Get-SourceRelativePath -FullName $_.FullName
            ))
        }

    foreach ($file in $sourceFiles) {
        $relativePath = Get-SourceRelativePath -FullName $file.FullName
        $targetPath = Join-Path $stagingPath $relativePath
        [IO.Directory]::CreateDirectory((Split-Path -Parent $targetPath)) | Out-Null
        [IO.File]::Copy($file.FullName, $targetPath, $true)
    }

    foreach ($composerFile in @('composer.json', 'composer.lock')) {
        [IO.File]::Copy(
            (Join-Path $sourcePath $composerFile),
            (Join-Path $stagingPath $composerFile),
            $true
        )
    }

    $composerCommand = Get-Command 'composer' -ErrorAction SilentlyContinue

    if ($null -eq $composerCommand) {
        throw 'Composer is required to build the production autoloader.'
    }

    & $composerCommand.Source install `
        --working-dir=$stagingPath `
        --no-dev `
        --classmap-authoritative `
        --no-interaction `
        --no-progress

    if ($LASTEXITCODE -ne 0) {
        throw 'Composer failed while creating the production autoloader.'
    }

    [IO.File]::Delete((Join-Path $stagingPath 'composer.json'))
    [IO.File]::Delete((Join-Path $stagingPath 'composer.lock'))

    $packageMainPath = Join-Path $stagingPath 'am-toolkit.php'

    $archiveStream = [IO.File]::Open(
        $temporaryArchivePath,
        [IO.FileMode]::CreateNew,
        [IO.FileAccess]::ReadWrite,
        [IO.FileShare]::None
    )

    $archive = [IO.Compression.ZipArchive]::new(
        $archiveStream,
        [IO.Compression.ZipArchiveMode]::Create,
        $false
    )

    try {
        # v0.9.0 reference: the main plugin file is the first ZIP entry.
        Add-ZipFile `
            -Archive $archive `
            -File (Get-Item -LiteralPath $packageMainPath) `
            -EntryName "$pluginRootName/am-toolkit.php"

        $files = Get-ChildItem -LiteralPath $stagingPath -Recurse -File |
            Where-Object {
                $_.FullName -ne $packageMainPath
            } |
            Sort-Object {
                $_.FullName.Substring($stagingPath.Length).TrimStart('\', '/').Replace('\', '/')
            }

        foreach ($file in $files) {
            $relativePath = (
                $file.FullName.Substring($stagingPath.Length).TrimStart('\', '/')
            ).Replace('\', '/')

            Add-ZipFile `
                -Archive $archive `
                -File $file `
                -EntryName "$pluginRootName/$relativePath"
        }
    } finally {
        $archive.Dispose()
        $archiveStream.Dispose()
    }

    & (Join-Path $PSScriptRoot 'validate-release.ps1') `
        -ArchivePath $temporaryArchivePath `
        -ExpectedVersion $version `
        -SkipFilenameCheck

    [IO.File]::Copy($temporaryArchivePath, $archivePath, $true)

    & (Join-Path $PSScriptRoot 'validate-release.ps1') `
        -ArchivePath $archivePath `
        -ExpectedVersion $version

    Write-Host ''
    Write-Host "Gotowa paczka: $archivePath" -ForegroundColor Green
} finally {
    if (Test-Path -LiteralPath $temporaryArchivePath) {
        [IO.File]::Delete($temporaryArchivePath)
    }

    if (Test-Path -LiteralPath $stagingContainer) {
        [IO.Directory]::Delete($stagingContainer, $true)
    }
}
