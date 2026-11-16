# Revisor del diagrama de casos de uso (spec 004). Solo lectura.
# Uso (PowerShell de 32 bits):  verificar.ps1 [-Archivo <ruta .eap>]
param([string]$Archivo = '')
if(-not $Archivo){ $Archivo = Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) '..\..\entregables\SIBEA_CasosDeUso.eap' }
$f = (Resolve-Path -LiteralPath $Archivo -ErrorAction SilentlyContinue).Path
if(-not $f){ $f = $Archivo }
$fallas = 0; $oks = 0
function OK($c,$msg){ if($c){ $script:oks++; "OK    $msg" } else { $script:fallas++; "FALLA $msg" } }
if(-not (Test-Path $f)){ "FALLA el archivo no existe: $f"; exit 1 }
$cn = New-Object System.Data.OleDb.OleDbConnection("Provider=Microsoft.Jet.OLEDB.4.0;Data Source=$f;Mode=Read"); $cn.Open()
function T($sql){ $da = New-Object System.Data.OleDb.OleDbDataAdapter($sql,$cn); $t = New-Object System.Data.DataTable; [void]$da.Fill($t); ,$t }

# Matriz v2: número -> nombre esperado del óvalo y actores según la columna ROL
# (A = Administrador, P = Aprendiz, S = Sistema; basta con que aparezca el principal)
$matriz = [ordered]@{
 '1.0'=@('Registrar usuario','A'); '1.1'=@('Consultar usuarios','A'); '1.2'=@('Modificar usuario','A'); '1.3'=@('Cambiar estado de usuario','A')
 '2.0'=@('Iniciar sesión','AP'); '2.1'=@('Cerrar sesión','AP'); '2.2'=@('Recuperar contraseña','AP'); '2.3'=@('Verificar administrador activo','S')
 '3.0'=@('Diligenciar formulario de postulación','P'); '3.1'=@('Cargar documentos de soporte','P'); '3.2'=@('Enviar postulación','P'); '3.3'=@('Cargar documentación adicional','P')
 '4.0'=@('Consultar listado de postulaciones','A'); '4.1'=@('Comparar datos y documentos','A'); '4.2'=@('Validar o rechazar postulación','A')
 '4.3'=@('Migrar información desde Excel','A'); '4.4'=@('Registrar auditoría','S'); '4.5'=@('Solicitar documentación adicional','A')
 '5.0'=@('Notificar validación','S'); '5.1'=@('Notificar rechazo','S'); '5.2'=@('Consultar estado de postulación','P'); '5.3'=@('Notificar aprobación','S')
 '5.4'=@('Notificar pago','S'); '5.5'=@('Notificar novedad','S'); '5.6'=@('Consultar notificaciones','P'); '5.7'=@('Enviar notificación por correo','S')
 '6.0'=@('Registrar pago','A'); '6.1'=@('Finalizar beneficio automáticamente','S'); '6.2'=@('Consultar historial de pagos','A'); '6.3'=@('Anular pago','A'); '6.4'=@('Consultar mis pagos','P')
 '7.0'=@('Gestionar beneficios','A'); '7.1'=@('Crear y modificar convocatorias','A'); '7.2'=@('Cambiar estado de convocatoria','A'); '7.3'=@('Consultar convocatorias abiertas','P')
 '8.0'=@('Aprobar postulación validada','A'); '8.1'=@('Rechazar postulación validada','A'); '8.2'=@('Crear asignación automáticamente','S'); '8.3'=@('Ajustar asignación','A')
 '8.4'=@('Suspender asignación','A'); '8.5'=@('Reactivar asignación','A'); '8.6'=@('Cancelar asignación','A'); '8.7'=@('Consultar asignaciones de beneficio','A')
 '9.0'=@('Registrar novedad','A'); '9.1'=@('Registrar novedad automática','S'); '9.2'=@('Consultar novedades','P')
}
# Módulo de la matriz v2 según el primer número del RF (columna Módulo)
$modulos = @('Gestión de usuarios','Autenticación y acceso','Registro de aprendices','Panel de administración',
 'Notificaciones','Seguimiento de pagos','Beneficios y convocatorias','Asignación de beneficios','Novedades')
$actorDe = @{ 'A'='Admin user'; 'P'='Aprendiz user'; 'S'='Sistema user' }

$objs = T "SELECT Object_ID, Object_Type, Name, ParentID, Package_ID, NType, Author, Version, Phase, Status FROM t_object WHERE Package_ID=4"
$porId = @{}; foreach($o in $objs){ $porId[[int]$o.Object_ID] = $o }
$rf = @($objs | ? { $_.Object_Type -eq 'UseCase' -and $_.Name -match '^RF-(\d+\.\d+) ' })
$nums = @($rf | % { ([regex]::Match($_.Name,'^RF-(\d+\.\d+)')).Groups[1].Value })

# CA-2: los 46 RF, con el número y el nombre de la matriz
OK ($nums.Count -eq 46) "CA-2 hay 46 óvalos RF (hay $($nums.Count))"
$faltan = @($matriz.Keys | ? { $nums -notcontains $_ }); OK ($faltan.Count -eq 0) "CA-2 no falta ningún RF ($($faltan -join ', '))"
$rep = @($nums | group | ? { $_.Count -gt 1 } | % Name); OK ($rep.Count -eq 0) "CA-2 ningún RF repetido ($($rep -join ', '))"
$sobran = @($nums | ? { -not $matriz.Contains($_) }); OK ($sobran.Count -eq 0) "CA-2 ningún RF inventado ($($sobran -join ', '))"
$malNombre = @($rf | ? { $n=([regex]::Match($_.Name,'^RF-(\d+\.\d+)')).Groups[1].Value; $matriz.Contains($n) -and $_.Name -ne "RF-$n $($matriz[$n][0])" } | % Name)
OK ($malNombre.Count -eq 0) "CA-2 cada óvalo tiene el nombre esperado ($($malNombre -join ' | '))"

# CA-3: nivel 1 con los 9 módulos de la matriz
$diags = T "SELECT Diagram_ID, Name, ParentID, Package_ID, Author, Version, cx, cy, Orientation FROM t_diagram"
$principal = @($diags | ? { $_.Name -eq 'Casos de uso principales' -and $_.Package_ID -eq 4 })
OK ($principal.Count -eq 1) "CA-3 existe un solo dibujo 'Casos de uso principales'"
$top = @($objs | ? { $_.ParentID -eq 0 })
$modId = @{}
foreach($m in $modulos){
  $o = @($top | ? { $_.Name -eq $m -and $_.Object_Type -eq 'UseCase' })
  OK ($o.Count -eq 1) "CA-3 módulo '$m' existe en el nivel 1"
  if($o.Count -eq 1){
    $modId[$m] = [int]$o[0].Object_ID
    OK ($o[0].NType -eq 8) "CA-3 '$m' es compuesto (icono ∞)"
    OK (@($diags | ? { $_.ParentID -eq $o[0].Object_ID }).Count -eq 1) "CA-3 '$m' tiene exactamente un dibujo adentro"
  }
}
if($principal.Count -eq 1){
  $en = T "SELECT o.Object_Type, o.Name FROM t_diagramobjects x INNER JOIN t_object o ON x.Object_ID=o.Object_ID WHERE x.Diagram_ID=$($principal[0].Diagram_ID)"
  OK (@($en | ? { $_.Object_Type -eq 'Actor' -and $_.Name -eq 'Admin' }).Count -eq 1) "CA-3 el nivel 1 muestra al actor Admin"
  OK (@($en | ? { $_.Object_Type -eq 'Boundary' -and $_.Name -eq 'SIBEA' }).Count -eq 1) "CA-3 el nivel 1 tiene el recuadro SIBEA"
  OK (@($en | ? { $_.Object_Type -eq 'UseCase' }).Count -eq 9) "CA-3 el nivel 1 muestra 9 óvalos"
}
$comp = T "SELECT o.Object_ID, o.Name, o.ea_guid AS G, d.ea_guid AS DG FROM t_object o INNER JOIN t_diagram d ON d.ParentID=o.Object_ID WHERE o.NType=8"
$xr = T "SELECT Client, Supplier FROM t_xref WHERE Name='DefaultDiagram' AND Type='element property'"
$sinVinculo = @($comp | ? { $c=$_; @($xr | ? { $_.Client -eq $c.G -and $_.Supplier -eq $c.DG }).Count -ne 1 } | % Name)
OK ($sinVinculo.Count -eq 0) "CA-3 cada óvalo compuesto tiene su vínculo DefaultDiagram ($($sinVinculo -join ', '))"
OK ($comp.Rows.Count -eq 9) "CA-3 hay 9 óvalos compuestos, uno por módulo (hay $($comp.Rows.Count))"

# CA-4: cada RF está guardado dentro del módulo que le da la matriz
$fueraDeModulo = @($rf | ? { $n=[int]([regex]::Match($_.Name,'^RF-(\d+)\.')).Groups[1].Value; $esperado=$modulos[$n-1]; $_.ParentID -ne $modId[$esperado] } | % Name)
OK ($fueraDeModulo.Count -eq 0) "CA-4 cada RF está dentro de su módulo de la matriz ($($fueraDeModulo -join ' | '))"

# CA-5: uniones
$dobj = T "SELECT x.Diagram_ID, x.Object_ID, x.RectLeft, x.RectRight, x.RectTop, x.RectBottom, o.Object_Type, o.Name, o.ParentID FROM t_diagramobjects x INNER JOIN t_object o ON x.Object_ID=o.Object_ID"
$malAparece = @($rf | ? { $id=$_.Object_ID; @($dobj | ? { $_.Object_ID -eq $id }).Count -ne 1 } | % Name)
OK ($malAparece.Count -eq 0) "CA-5 cada RF aparece en un solo dibujo ($($malAparece -join ' | '))"
$con = T "SELECT c.Connector_ID, c.Connector_Type, c.Stereotype, c.SubType, c.Direction, c.LineColor, c.Start_Object_ID AS SA, c.End_Object_ID AS SB, a.Object_Type AS TA, b.Object_Type AS TB, a.Name AS NA, b.Name AS NB, a.ParentID AS PA, b.ParentID AS PB FROM (t_connector c INNER JOIN t_object a ON c.Start_Object_ID=a.Object_ID) INNER JOIN t_object b ON c.End_Object_ID=b.Object_ID"
$ext = @($con | ? { $_.Stereotype -eq 'extend' })
OK ($ext.Count -gt 0) "CA-5 hay flechas extend ($($ext.Count))"
OK (@($ext | ? { $_.Connector_Type -ne 'UseCase' -or $_.SubType -ne 'Extends' -or $_.Direction -ne 'Source -> Destination' -or $_.TA -ne 'UseCase' -or $_.TB -ne 'UseCase' }).Count -eq 0) "CA-5 todas las extend van de óvalo a óvalo con el formato de Alfasocial"
$asoc = @($con | ? { $_.Connector_Type -eq 'Association' })
# el óvalo que hace «extend» es secundario: no se une también al actor
$extConActor = @($ext | ? { $s=$_.SA; @($asoc | ? { $_.SB -eq $s }).Count -gt 0 } | % NA)
OK ($extConActor.Count -eq 0) "CA-5 los óvalos secundarios (extend) no se unen además a un actor ($($extConActor -join ' | '))"
$sueltos = @($rf | ? { $id=$_.Object_ID; @($con | ? { $_.SA -eq $id -or $_.SB -eq $id }).Count -eq 0 } | % Name)
OK ($sueltos.Count -eq 0) "CA-5 ningún RF suelto ($($sueltos -join ' | '))"
# actor según ROL: el RF (o el óvalo central al que extiende) se une al actor de la matriz
$malActor = @()
foreach($r in $rf){
  $n = ([regex]::Match($r.Name,'^RF-(\d+\.\d+)')).Groups[1].Value
  $ids = @([int]$r.Object_ID) + @($ext | ? { $_.SA -eq $r.Object_ID } | % { [int]$_.SB })
  $ids += @($ext | ? { $ids -contains [int]$_.SA } | % { [int]$_.SB })   # cadenas de extend
  foreach($letra in $matriz[$n][1].ToCharArray()){
    $nombre = $actorDe[[string]$letra]
    if(@($asoc | ? { $ids -contains [int]$_.SB -and $_.NA -eq $nombre -and $_.TA -eq 'Actor' }).Count -eq 0){ $malActor += "RF-$n sin $nombre" }
  }
}
OK ($malActor.Count -eq 0) "CA-5 cada RF llega al actor de su ROL en la matriz ($($malActor -join ' | '))"
$actores = @($objs | ? { $_.Object_Type -eq 'Actor' -and $_.ParentID -ne 0 })
$actorSinCaso = @($actores | ? { $id=$_.Object_ID; @($asoc | ? { $_.SA -eq $id }).Count -eq 0 } | % { "$($_.Name) en $($porId[[int]$_.ParentID].Name)" })
OK ($actorSinCaso.Count -eq 0) "CA-5 todo actor se une al menos a un caso de uso ($($actorSinCaso -join ' | '))"
$dep = @($con | ? { $_.Connector_Type -eq 'Dependency' })
OK (@($dep | ? { $_.TA -ne 'Actor' -or $_.TB -ne 'Actor' -or $_.NA -ne 'Admin user' -or $_.PA -ne $_.PB }).Count -eq 0) "CA-5 las dependencias solo van de Admin user a otro actor del mismo módulo"
$sinDep = @($actores | ? { $_.Name -ne 'Admin user' } | ? { $p=$_.ParentID; $id=$_.Object_ID; @($actores | ? { $_.ParentID -eq $p -and $_.Name -eq 'Admin user' }).Count -gt 0 -and @($dep | ? { $_.SB -eq $id }).Count -ne 1 } | % { "$($_.Name) en $($porId[[int]$_.ParentID].Name)" })
OK ($sinDep.Count -eq 0) "CA-5 Admin user lanza una dependencia a cada otro actor de su dibujo ($($sinDep -join ' | '))"
OK (@($asoc | ? { $_.Direction -ne 'Unspecified' }).Count -eq 0) "CA-5 asociaciones sin dirección, como en Alfasocial"

# CA-5: geometría (actores a la izquierda del recuadro, óvalos dentro) y nada encimado ni fuera de la hoja
$problemasGeo = @()
foreach($d in $diags){
  $en = @($dobj | ? { $_.Diagram_ID -eq $d.Diagram_ID })
  $caja = @($en | ? { $_.Object_Type -eq 'Boundary' })
  if($d.Package_ID -eq 4){
    if($caja.Count -ne 1){ $problemasGeo += "'$($d.Name)' tiene $($caja.Count) recuadros" }
    else {
      $b = $caja[0]
      foreach($x in @($en | ? { $_.Object_Type -eq 'Actor' })){ if($x.RectRight -gt $b.RectLeft){ $problemasGeo += "'$($d.Name)': actor $($x.Name) no está a la izquierda del recuadro" } }
      foreach($x in @($en | ? { $_.Object_Type -eq 'UseCase' })){ if($x.RectLeft -lt $b.RectLeft -or $x.RectRight -gt $b.RectRight -or $x.RectTop -gt $b.RectTop -or $x.RectBottom -lt $b.RectBottom){ $problemasGeo += "'$($d.Name)': $($x.Name) se sale del recuadro" } }
    }
  }
  $items = @($en | ? { $_.Object_Type -eq 'UseCase' -or $_.Object_Type -eq 'Actor' })
  for($i=0;$i -lt $items.Count;$i++){ for($j=$i+1;$j -lt $items.Count;$j++){
    $a=$items[$i];$c=$items[$j]
    $sl = [Math]::Min($a.RectRight,$c.RectRight) - [Math]::Max($a.RectLeft,$c.RectLeft)
    $sv = [Math]::Min(-$a.RectBottom,-$c.RectBottom) - [Math]::Max(-$a.RectTop,-$c.RectTop)
    if($sl -gt 0 -and $sv -gt 0){ $problemasGeo += "'$($d.Name)': $($a.Name) se encima con $($c.Name)" }
  }}
  if(@($en | ? { $_.RectRight -gt 820 -or -$_.RectBottom -gt 1160 -or $_.RectLeft -lt 0 }).Count -gt 0){ $problemasGeo += "'$($d.Name)' se sale de la hoja" }
}
OK ($problemasGeo.Count -eq 0) "CA-5 geometría correcta en todos los dibujos ($($problemasGeo -join ' | '))"

# CA-6
$nuestros = @($objs | ? { $_.Object_Type -in 'UseCase','Actor','Boundary' })
OK (@($nuestros | ? { $_.Author -ne 'SENA' -or $_.Version -ne '1.0' -or $_.Phase -ne '1.0' }).Count -eq 0) "CA-6 autor SENA, versión 1.0 y fase 1.0 en todos los elementos"
OK (@($nuestros | ? { $_.Object_Type -eq 'Boundary' -and $_.Status -ne 'Propuesto' }).Count -eq 0) "CA-6 recuadros en estado Propuesto"
OK (@($nuestros | ? { $_.Object_Type -ne 'Boundary' -and -not [string]::IsNullOrEmpty([string]$_.Status) }).Count -eq 0) "CA-6 óvalos y actores con estado vacío"
$d4 = @($diags | ? { $_.Package_ID -eq 4 })
OK (@($d4 | ? { $_.Author -ne 'SENA' -or $_.Version -ne '1.0' -or $_.cx -ne 827 -or $_.cy -ne 1169 -or $_.Orientation -ne 'P' }).Count -eq 0) "CA-6 dibujos con autor SENA, versión 1.0 y hoja A4 vertical"
$col = T "SELECT Object_ID FROM t_object WHERE Package_ID=4 AND (Backcolor<>-1 OR Fontcolor<>-1 OR Bordercolor<>-1)"
OK ($col.Rows.Count -eq 0) "CA-6 elementos con colores por defecto"
OK (@($con | ? { $_.LineColor -ne -1 }).Count -eq 0) "CA-6 líneas con color por defecto"

# CA-7
$hijosNivel1 = @($objs | ? { $_.ParentID -eq 0 -and $_.Object_Type -in 'UseCase','Actor','Boundary' })
OK ($hijosNivel1.Count -eq 11) "CA-7 en la raíz solo están Admin, el recuadro SIBEA y los 9 módulos (hay $($hijosNivel1.Count))"
OK (@($actores | ? { $_.Name -notin 'Admin user','Aprendiz user','Sistema user' }).Count -eq 0) "CA-7 actores anidados con nombres Admin user / Aprendiz user / Sistema user"

# Limpieza: nada huérfano heredado de la plantilla
$huerf = T "SELECT Connector_ID FROM t_connector WHERE Start_Object_ID NOT IN (SELECT Object_ID FROM t_object) OR End_Object_ID NOT IN (SELECT Object_ID FROM t_object)"
OK ($huerf.Rows.Count -eq 0) "sin conectores huérfanos (hay $($huerf.Rows.Count))"
$lh = T "SELECT Instance_ID FROM t_diagramlinks WHERE DiagramID NOT IN (SELECT Diagram_ID FROM t_diagram)"
OK ($lh.Rows.Count -eq 0) "sin enlaces de dibujo huérfanos (hay $($lh.Rows.Count))"
$alfa = T "SELECT Object_ID FROM t_object WHERE Name LIKE '%lfa%' OR Name LIKE '%horas%'"
OK ($alfa.Rows.Count -eq 0) "sin elementos que nombren Alfasocial u horas"

$cn.Close()
""; "RESULTADO: $oks OK / $fallas FALLA"
if($fallas -gt 0){ exit 1 }
