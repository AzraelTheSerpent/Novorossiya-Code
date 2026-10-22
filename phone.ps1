param([int]$Port = 8000)

$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

$exe = Join-Path $PSScriptRoot 'runtime\cloudflared.exe'
if (-not (Test-Path -LiteralPath $exe)) {
    Write-Host 'cloudflared.exe was not found in the runtime folder.' -ForegroundColor Red
    exit 1
}

$startInfo = [System.Diagnostics.ProcessStartInfo]::new()
$startInfo.FileName = $exe
$startInfo.Arguments = "tunnel --url http://127.0.0.1:$Port"
$startInfo.UseShellExecute = $false
$startInfo.RedirectStandardOutput = $true
$startInfo.RedirectStandardError = $true
$startInfo.CreateNoWindow = $false

$process = [System.Diagnostics.Process]::Start($startInfo)
$url = $null

try {
    while (-not $process.HasExited) {
        $line = $process.StandardError.ReadLine()
        if ($null -eq $line) { break }

        if (-not $url -and $line -match 'https://[a-z0-9-]+\.trycloudflare\.com') {
            $url = $Matches[0]
            Write-Host ''
            Write-Host '============================================================' -ForegroundColor Green
            Write-Host ' OPEN THIS EXACT ADDRESS ON YOUR PHONE:' -ForegroundColor Green
            Write-Host " $url" -ForegroundColor Yellow
            Write-Host ' It has been copied to the clipboard.' -ForegroundColor Green
            Write-Host '============================================================' -ForegroundColor Green
            Write-Host ''
            try { Set-Clipboard -Value $url } catch { }
        } elseif ($line -match 'Registered tunnel|quick Tunnel|ERR|failed') {
            Write-Host $line
        }
    }
} finally {
    if (-not $process.HasExited) {
        $process.Kill()
    }
}
