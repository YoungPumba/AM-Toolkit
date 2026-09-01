[CmdletBinding()]
param(
    [string] $SourceDirectory
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($SourceDirectory)) {
    $SourceDirectory = Split-Path -Parent $PSScriptRoot
}

$sourcePath = (Resolve-Path -LiteralPath $SourceDirectory).Path
$testRoot = Join-Path ([IO.Path]::GetTempPath()) (
    "am-toolkit-reproducible-release-$PID"
)
$firstOutput = Join-Path $testRoot 'first'
$secondOutput = Join-Path $testRoot 'second'
$builder = Join-Path $PSScriptRoot 'build-release.ps1'

try {
    & $builder `
        -SourceDirectory $sourcePath `
        -OutputDirectory $firstOutput `
        -Force

    & $builder `
        -SourceDirectory $sourcePath `
        -OutputDirectory $secondOutput `
        -Force

    $firstArchives = @(Get-ChildItem -LiteralPath $firstOutput -Filter '*.zip' -File)
    $secondArchives = @(Get-ChildItem -LiteralPath $secondOutput -Filter '*.zip' -File)

    if ($firstArchives.Count -ne 1 -or $secondArchives.Count -ne 1) {
        throw 'Each reproducibility build must produce exactly one ZIP archive.'
    }

    if ($firstArchives[0].Name -ne $secondArchives[0].Name) {
        throw 'Reproducibility builds produced different archive names.'
    }

    $firstHash = (
        Get-FileHash -Algorithm SHA256 -LiteralPath $firstArchives[0].FullName
    ).Hash
    $secondHash = (
        Get-FileHash -Algorithm SHA256 -LiteralPath $secondArchives[0].FullName
    ).Hash

    if ($firstHash -ne $secondHash) {
        throw "Release archives are not reproducible: $firstHash != $secondHash"
    }

    Write-Host (
        'OK: reproducible release {0} | SHA-256 {1}' -f `
            $firstArchives[0].Name,
            $firstHash
    ) -ForegroundColor Green
} finally {
    if (Test-Path -LiteralPath $testRoot) {
        [IO.Directory]::Delete($testRoot, $true)
    }
}
