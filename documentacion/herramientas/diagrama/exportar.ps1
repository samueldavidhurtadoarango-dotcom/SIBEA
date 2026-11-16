# Saca un PNG de cada dibujo del .eap. Uso (PowerShell de 32 bits):
#   exportar.ps1 [-Archivo <ruta .eap>] -Carpeta <carpeta de salida>
param(
  [string]$Archivo = '',
  [Parameter(Mandatory=$true)][string]$Carpeta
)
if(-not $Archivo){ $Archivo = Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) '..\..\entregables\SIBEA_CasosDeUso.eap' }
$f = (Resolve-Path -LiteralPath $Archivo).Path
$out = [System.IO.Path]::GetFullPath($Carpeta)
New-Item -ItemType Directory -Force -Path $out | Out-Null
$r = New-Object -ComObject EA.Repository
try {
  if(-not $r.OpenFile($f)){ "NO ABRE"; exit 1 }
  $pi = $r.GetProjectInterface()
  $sql = $r.SQLQuery("SELECT Diagram_ID FROM t_diagram")
  $ids = [regex]::Matches($sql,'<Diagram_ID>(\d+)</Diagram_ID>') | % { [int]$_.Groups[1].Value }
  foreach($id in $ids){
    $d = $r.GetDiagramByID($id)
    $name = ("{0:D2}_" -f $d.DiagramID) + ($d.Name -replace '[^\w]','_') + ".png"
    $ok = $pi.PutDiagramImageToFile($pi.GUIDtoXML($d.DiagramGUID), (Join-Path $out $name), 1)
    "$ok $name objs=$($d.DiagramObjects.Count) links=$($d.DiagramLinks.Count)"
  }
} finally { $r.CloseFile(); $r.Exit() }
