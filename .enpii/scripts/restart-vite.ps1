$ErrorActionPreference = 'Continue'
$cmd = "docker exec -d siupknext-node-1 sh -c `"cd /var/www/html && npm run dev > /tmp/vite.log 2>&1`""
Write-Host ("exec: " + $cmd)
Invoke-Expression $cmd | Out-Null
for ($i = 0; $i -lt 30; $i++) {
    Start-Sleep -Seconds 1
    try {
        $r = Invoke-WebRequest 'http://localhost:5174/@vite/client' -UseBasicParsing -TimeoutSec 2
        if ($r.StatusCode -eq 200) { Write-Host ("ready after " + $i + "s"); break }
    } catch {
        Write-Host ("wait " + $i)
    }
}
Write-Host 'DONE'
