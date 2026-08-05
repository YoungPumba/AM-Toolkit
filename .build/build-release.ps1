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
            -File (Get-Item -LiteralPath $mainPluginPath) `
            -EntryName "$pluginRootName/am-toolkit.php"

        $files = Get-ChildItem -LiteralPath $sourcePath -Recurse -File |
            Where-Object {
                $_.FullName -ne $mainPluginPath -and
                -not (Test-IsBuildFile -RelativePath (
                    Get-SourceRelativePath -FullName $_.FullName
                ))
            } |
            Sort-Object {
                (Get-SourceRelativePath -FullName $_.FullName).Replace('\', '/')
            }

        foreach ($file in $files) {
            $relativePath = (
                Get-SourceRelativePath -FullName $file.FullName
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
}
