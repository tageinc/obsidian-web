<#
.SYNOPSIS
Reports possible secret exposure by file, commit, and variable name only.

.DESCRIPTION
The script deliberately never writes matched lines or values to stdout. It
checks tracked working-tree files and every Git revision that contains .env.
It cannot inspect unconnected hosts, secret stores, backups, or deployment
systems; perform those checks with their approved access paths.
#>

[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$seenFindings = [System.Collections.Generic.HashSet[string]]::new()
$secretNamePattern = '(?i)(^|_)(key|secret|token|password|passwd|credential|api_login_id|sid)(_|$)|^(app_key|transaction_key|twilio_from|db_username)$'
$literalPatterns = @(
    '(?i)(api[_-]?key|secret|token|password|private[_-]?key)\s*[=:]\s*[^\s"'']+',
    '(?i)authorization\s*[:=]\s*(bearer|basic)\s+[^\s"'']+'
)

function Write-Finding([string]$Location, [string]$Rule) {
    $finding = "$Location [$Rule]"
    if ($seenFindings.Add($finding)) {
        Write-Output $finding
    }
}

function Test-Content([string]$Location, [string[]]$Lines) {
    foreach ($line in $Lines) {
        if ($line -match '^\s*([A-Za-z_][A-Za-z0-9_]*)=(.+)$') {
            $variableName = $Matches[1]
            if ($variableName -match $secretNamePattern) {
                Write-Finding $Location "environment variable: $variableName"
            }
        }
        foreach ($pattern in $literalPatterns) {
            if ($line -match $pattern) {
                Write-Finding $Location 'possible literal secret'
                break
            }
        }
    }
}

$root = (git -c "safe.directory=$((Get-Location).Path)" rev-parse --show-toplevel).Trim()
Push-Location $root
try {
    $tracked = git -c "safe.directory=$root" ls-files
    foreach ($relativePath in $tracked) {
        if ($relativePath -match '^(vendor|node_modules)/') {
            continue
        }
        $extension = [System.IO.Path]::GetExtension($relativePath).ToLowerInvariant()
        if ($extension -in @('.phar', '.png', '.jpg', '.jpeg', '.gif', '.zip', '.pdf')) {
            continue
        }
        if (Test-Path -LiteralPath $relativePath -PathType Leaf) {
            Test-Content $relativePath ([System.IO.File]::ReadAllLines((Join-Path $root $relativePath)))
        }
    }

    foreach ($commit in (git -c "safe.directory=$root" rev-list --all -- .env)) {
        $content = git -c "safe.directory=$root" show "$commit`:.env" 2>$null
        if ($LASTEXITCODE -eq 0) {
            Test-Content "${commit}:.env" $content
        }
    }
} finally {
    Pop-Location
}
