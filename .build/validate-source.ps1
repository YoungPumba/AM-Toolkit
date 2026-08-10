[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$sourcePath = (Split-Path -Parent $PSScriptRoot)
$mainPath = Join-Path $sourcePath 'am-toolkit.php'
$pluginPath = Join-Path $sourcePath 'src\Core\Plugin.php'
$composerPath = Join-Path $sourcePath 'composer.json'

$main = Get-Content -LiteralPath $mainPath -Raw -Encoding UTF8
$plugin = Get-Content -LiteralPath $pluginPath -Raw -Encoding UTF8
$composer = Get-Content -LiteralPath $composerPath -Raw -Encoding UTF8 | ConvertFrom-Json

if ($composer.autoload.'psr-4'.'AMToolkit\' -ne 'src/') {
    throw 'Composer PSR-4 mapping must map AMToolkit\ to src/.'
}

$headerVersion = [regex]::Match(
    $main,
    '(?m)^\s*\*\s*Version:\s*([^\r\n]+)\s*$'
).Groups[1].Value.Trim()
$constantVersion = [regex]::Match(
    $main,
    "define\('AM_TOOLKIT_VERSION',\s*'([^']+)'\)"
).Groups[1].Value
$classVersion = [regex]::Match(
    $plugin,
    "public const VERSION = '([^']+)'"
).Groups[1].Value

if (
    [string]::IsNullOrWhiteSpace($headerVersion) -or
    $headerVersion -ne $constantVersion -or
    $headerVersion -ne $classVersion
) {
    throw (
        'Version mismatch: header={0}, constant={1}, class={2}' -f
        $headerVersion,
        $constantVersion,
        $classVersion
    )
}

$requires = [regex]::Matches(
    $main,
    "require_once\s+AM_TOOLKIT_PATH\s*\.\s*'([^']+)'"
)

foreach ($match in $requires) {
    $relativePath = $match.Groups[1].Value.Replace('/', '\')
    $requiredPath = Join-Path $sourcePath $relativePath

    if (-not (Test-Path -LiteralPath $requiredPath -PathType Leaf)) {
        throw "Required PHP file does not exist: $relativePath"
    }
}

$phpFiles = @(
    Get-ChildItem -LiteralPath $sourcePath -Recurse -File -Filter '*.php' |
        Where-Object {
            -not $_.FullName.Contains('\.build\') -and
            -not $_.FullName.Contains('\vendor\')
        }
)

$phpCommand = Get-Command 'php' -ErrorAction SilentlyContinue

if ($null -eq $phpCommand) {
    throw 'PHP CLI is not available in PATH.'
}

foreach ($file in $phpFiles) {
    $syntaxResult = & $phpCommand.Source '-l' $file.FullName 2>&1

    if ($LASTEXITCODE -ne 0) {
        throw (
            "PHP syntax error in $($file.FullName):`n" +
            ($syntaxResult -join "`n")
        )
    }

    $bytes = [IO.File]::ReadAllBytes($file.FullName)

    if (
        $bytes.Length -ge 3 -and
        $bytes[0] -eq 0xEF -and
        $bytes[1] -eq 0xBB -and
        $bytes[2] -eq 0xBF
    ) {
        throw "UTF-8 BOM detected before PHP opening tag: $($file.FullName)"
    }

    $contents = Get-Content -LiteralPath $file.FullName -Raw -Encoding UTF8

    if ($contents -notmatch '^\s*<\?php') {
        throw "Missing PHP opening tag: $($file.FullName)"
    }
}

$accessFiles = @(
    'src\Core\Installer.php',
    'src\Core\MigrationInterface.php',
    'src\Core\MigrationRunner.php',
    'src\Modules\Access\AccessSchema.php',
    'src\Modules\Access\Migrations\CreateAccessTables.php',
    'src\Modules\Access\EntitlementStore.php',
    'src\Modules\Access\ActivityEventStore.php',
    'src\Modules\Access\WpdbEntitlementStore.php',
    'src\Modules\Access\WpdbActivityEventStore.php',
    'src\Modules\Access\AccessManager.php',
    'src\Modules\Access\Access.php'
)

foreach ($relativePath in $accessFiles) {
    if (-not (Test-Path -LiteralPath (Join-Path $sourcePath $relativePath))) {
        throw "AM Access Core file is missing: $relativePath"
    }
}

Write-Host (
    'OK: AM Toolkit {0} | {1} PHP files | {2} required files' -f
    $headerVersion,
    $phpFiles.Count,
    $requires.Count
) -ForegroundColor Green
