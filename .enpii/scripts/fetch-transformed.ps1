$ErrorActionPreference = 'Continue'
$u = 'http://localhost:5174/resources/js/Pages/Admin/Integration.vue?vue&type=script&setup=true&lang.js&t=' + (Get-Random)
$r = Invoke-WebRequest $u -UseBasicParsing -TimeoutSec 10 -Headers @{'Cache-Control'='no-cache'}
Write-Host ('http=' + $r.StatusCode + ' size=' + $r.Content.Length)
$lines = $r.Content -split "`n"
$count = [Math]::Min(8, $lines.Count)
for ($i = 0; $i -lt $count; $i++) {
  Write-Host ($lines[$i])
}
