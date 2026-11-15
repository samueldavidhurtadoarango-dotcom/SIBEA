# Construye el diagrama de casos de uso de SIBEA con la API de Enterprise Architect.
# Uso (PowerShell de 32 bits):
#   construir.ps1 -Plantilla <alfa.eap> -Salida <SIBEA_CasosDeUso.eap>
# Copia la plantilla (portada, paquetes y estilos), vacía su contenido, dibuja todo con
# parte2.ps1 y al final compacta el archivo. OJO: sobrescribe -Salida si ya existe.
param(
  [Parameter(Mandatory=$true)][string]$Plantilla,
  [Parameter(Mandatory=$true)][string]$Salida
)
$ErrorActionPreference = 'Stop'
$plantilla = (Resolve-Path -LiteralPath $Plantilla).Path
$salida = [System.IO.Path]::GetFullPath($Salida)
New-Item -ItemType Directory -Force -Path (Split-Path $salida) | Out-Null
Copy-Item -LiteralPath $plantilla -Destination $salida -Force
Set-ItemProperty -LiteralPath $salida -Name IsReadOnly -Value $false
$r = New-Object -ComObject EA.Repository
if(-not $r.OpenFile($salida)){ throw "No abre $salida" }
try {
$pkg = $r.GetPackageByID(4)   # 'Casos de uso principales'

# 1) Vaciar el contenido de Alfasocial (los elementos se llevan sus hijos, dibujos y flechas)
for($i = $pkg.Elements.Count - 1; $i -ge 0; $i--){ $pkg.Elements.DeleteAt($i, $false) }
$pkg.Elements.Refresh()
$principal = $r.GetDiagramByID(3)
for($i = $principal.DiagramObjects.Count - 1; $i -ge 0; $i--){ $principal.DiagramObjects.DeleteAt($i, $false) }
$principal.DiagramObjects.Refresh()

# ---------- utilidades ----------
function Nuevo($nombre, $tipo, $padre){
  $e = $pkg.Elements.AddNew($nombre, $tipo)
  $e.Author = 'SENA'; $e.Version = '1.0'; $e.Phase = '1.0'
  $e.Status = $(if($tipo -eq 'Boundary'){ 'Propuesto' } else { '' })
  if($padre){ $e.ParentID = $padre.ElementID }
  [void]$e.Update(); $pkg.Elements.Refresh()
  $r.Execute("UPDATE t_object SET Status='" + $(if($tipo -eq 'Boundary'){'Propuesto'}else{''}) + "' WHERE Object_ID=" + $e.ElementID)
  return $e
}
function Dibujo($nombre, $duenio){
  $d = $duenio.Diagrams.AddNew($nombre, 'Use Case')
  $d.Author = 'SENA'; $d.Version = '1.0'
  # misma hoja y estilo que los dibujos hijos de Alfasocial (A4 vertical, UML 2.1)
  $d.cx = 827; $d.cy = 1169
  $d.StyleEx = $principal.StyleEx
  $d.ExtendedStyle = ($principal.ExtendedStyle -replace 'PPgs\.cx=0','PPgs.cx=1' -replace 'PPgs\.cy=0','PPgs.cy=1')
  [void]$d.Update(); $duenio.Diagrams.Refresh()
  $r.Execute("UPDATE t_diagram SET cx=827, cy=1169 WHERE Diagram_ID=" + $d.DiagramID)
  # óvalo compuesto: NType=8 + vínculo DefaultDiagram (así lo guarda Alfasocial; dibuja el icono ∞)
  $r.Execute("UPDATE t_object SET NType=8 WHERE Object_ID=" + $duenio.ElementID)
  $x = '{' + [guid]::NewGuid().ToString().ToUpper() + '}'
  $r.Execute("INSERT INTO t_xref (XrefID, Name, Type, Visibility, Partition, Client, Supplier) VALUES ('$x','DefaultDiagram','element property','Public','0','$($duenio.ElementGUID)','$($d.DiagramGUID)')")
  return $d
}
function Poner($d, $e, $l, $t, $w, $h){
  $o = $d.DiagramObjects.AddNew(("l={0};r={1};t={2};b={3};" -f [int]$l, [int]($l+$w), [int]$t, [int]($t+$h)), '')
  $o.ElementID = $e.ElementID; [void]$o.Update(); $d.DiagramObjects.Refresh()
}
function Tam($nombre){
  if($nombre.Length -gt 40){ return @(130, 85) }
  if($nombre.Length -gt 28){ return @(118, 76) }
  return @(105, 70)
}
function Unir($desde, $hacia, $tipo){
  switch($tipo){
    'asoc'   { $c = $desde.Connectors.AddNew('', 'Association'); $c.Direction = 'Unspecified' }
    'extend' { $c = $desde.Connectors.AddNew('', 'UseCase'); $c.Stereotype = 'extend'; $c.Subtype = 'Extends'; $c.Direction = 'Source -> Destination' }
    'dep'    { $c = $desde.Connectors.AddNew('', 'Dependency'); $c.Direction = 'Source -> Destination' }
  }
  $c.SupplierID = $hacia.ElementID; [void]$c.Update(); $desde.Connectors.Refresh()
}


# Los dibujos y la limpieza final viven en parte2.ps1 (se ejecuta en este mismo ámbito)
. (Join-Path $PSScriptRoot 'parte2.ps1')
} finally { $r.CloseFile(); $r.Exit() }

# Compactar con el motor Jet 4.0 (quita del archivo los textos borrados de la plantilla).
# Se hace en un PowerShell hijo (32 bits) porque, tras cerrar EA, el motor JRO falla en este mismo proceso.
$tmp = [System.IO.Path]::ChangeExtension($salida, '.compacto.tmp')
if(Test-Path $tmp){ Remove-Item $tmp -Force }
$cmd = "`$je = New-Object -ComObject JRO.JetEngine; " +
  "`$je.CompactDatabase('Provider=Microsoft.Jet.OLEDB.4.0;Data Source=$salida', " +
  "'Provider=Microsoft.Jet.OLEDB.4.0;Data Source=$tmp;Jet OLEDB:Engine Type=4')"
Start-Sleep -Seconds 3
& (Join-Path $PSHOME 'powershell.exe') -NoProfile -ExecutionPolicy Bypass -Command $cmd
if((Test-Path $tmp) -and ((Get-Item $tmp).Length -gt 0)){ Move-Item -LiteralPath $tmp -Destination $salida -Force; "COMPACTADO=True" }
else { "COMPACTAR falló: el archivo queda sin compactar (sigue siendo válido)" }
