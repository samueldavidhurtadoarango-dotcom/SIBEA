# Dibujo de requisitos, con el molde de Alfasocial:
# actores a la izquierda, recuadro a la derecha, óvalo central unido al actor, el resto con «extend».
# $ovalos: lista de @(rf, nombre, columna, fila, union)
#   union = 'actor:Admin user'  (o varios: 'actor:Admin user+Aprendiz user')
#         | 'ext:6.2'           (o varios destinos: 'ext:5.0,5.1')
# $paso: distancia vertical entre filas.
function DibujoRequisitos($duenio, $titulo, $ovalos, $paso = 125){
  $d = Dibujo $titulo $duenio
  $filas = ($ovalos | % { $_[3] } | Measure-Object -Maximum).Maximum + 1
  $bTop = 40; $bBot = 130 + $filas * $paso
  $caja = Nuevo $titulo 'Boundary' $duenio
  Poner $d $caja 330 $bTop 460 ($bBot - $bTop)
  $porRf = @{}; $actores = [ordered]@{}
  foreach($o in $ovalos){
    $nombre = "RF-$($o[0]) $($o[1])"
    $e = Nuevo $nombre 'UseCase' $duenio
    $wh = Tam $nombre; $cx = @(440, 670)[$o[2]]; $cy = 120 + $o[3] * $paso
    Poner $d $e ($cx - $wh[0]/2) ($cy - $wh[1]/2) $wh[0] $wh[1]
    $porRf[$o[0]] = $e
    if($o[4] -like 'actor:*'){ foreach($a in $o[4].Substring(6).Split('+')){ if(-not $actores.Contains($a)){ $actores[$a] = $cy } } }
  }
  # actores: cada uno a la altura de su primer óvalo; el Admin user siempre arriba.
  # Como en Alfasocial, si hay dos actores debajo del Admin van uno a cada lado,
  # para que las flechas punteadas del Admin no pasen por encima de otro actor.
  $creados = @{}
  $hayAdmin = $actores.Contains('Admin user')
  $otros = @($actores.Keys | ? { $_ -ne 'Admin user' } | Sort-Object { $actores[$_] })
  if($hayAdmin){
    $e = Nuevo 'Admin user' 'Actor' $duenio; $creados['Admin user'] = $e
    Poner $d $e 150 ($actores['Admin user'] - 45) 45 90
  }
  $ultimoY = $(if($hayAdmin){ $actores['Admin user'] } else { -999 })
  $k = 0
  foreach($a in $otros){
    $e = Nuevo $a 'Actor' $duenio; $creados[$a] = $e
    $cy = $actores[$a]
    if($hayAdmin -and $cy -le $actores['Admin user'] + 90){ $cy = $actores['Admin user'] + 200 }
    if($cy -lt $ultimoY + 110){ $cy = $ultimoY + 110 }   # nunca dos actores a la misma altura
    $l = $(if(-not $hayAdmin){ 120 } elseif($otros.Count -eq 1){ 150 } else { @(60, 240)[$k % 2] })
    Poner $d $e $l ($cy - 45) 45 90
    $ultimoY = $cy; $k++
  }
  foreach($o in $ovalos){
    $e = $porRf[$o[0]]
    if($o[4] -like 'actor:*'){ foreach($a in $o[4].Substring(6).Split('+')){ Unir $creados[$a] $e 'asoc' } }
    else { foreach($rf in $o[4].Substring(4).Split(',')){ Unir $e $porRf[$rf] 'extend' } }
  }
  if($creados.ContainsKey('Admin user')){
    foreach($a in $creados.Keys){ if($a -ne 'Admin user'){ Unir $creados['Admin user'] $creados[$a] 'dep' } }
  }
}

# ---------- Nivel 1: un óvalo por cada módulo de la matriz v2 (9) ----------
$admin = Nuevo 'Admin' 'Actor' $null
$sistema = Nuevo 'SIBEA' 'Boundary' $null
$nombresModulos = @('Gestión de usuarios','Autenticación y acceso','Registro de aprendices','Panel de administración',
  'Notificaciones','Seguimiento de pagos','Beneficios y convocatorias','Asignación de beneficios','Novedades')
$mod = @{}
Poner $principal $sistema 330 30 330 920
$i = 0
foreach($n in $nombresModulos){
  $e = Nuevo $n 'UseCase' $null; $mod[$n] = $e
  # una sola columna: así ninguna línea del Admin cruza por encima de otro óvalo
  $cx = 495; $cy = 90 + $i * 100
  Poner $principal $e ($cx - 85) ($cy - 40) 170 80
  Unir $admin $e 'asoc'; $i++
}
Poner $principal $admin 130 445 45 90

DibujoRequisitos $mod['Gestión de usuarios'] 'Gestión de usuarios' @(
  @('1.1','Consultar usuarios',0,2,'actor:Admin user'),
  @('1.0','Registrar usuario',0,0,'ext:1.1'),
  @('1.2','Modificar usuario',1,1,'ext:1.1'),
  @('1.3','Cambiar estado de usuario',1,3,'ext:1.1'))
DibujoRequisitos $mod['Autenticación y acceso'] 'Autenticación y acceso' @(
  @('2.0','Iniciar sesión',0,1,'actor:Admin user+Aprendiz user'),
  @('2.1','Cerrar sesión',1,0,'ext:2.0'),
  @('2.2','Recuperar contraseña',1,2,'ext:2.0'),
  @('2.3','Verificar administrador activo',0,3,'actor:Sistema user'))
DibujoRequisitos $mod['Registro de aprendices'] 'Registro de aprendices' @(
  @('3.0','Diligenciar formulario de postulación',0,1,'actor:Aprendiz user'),
  @('3.1','Cargar documentos de soporte',1,0,'ext:3.0'),
  @('3.2','Enviar postulación',1,2,'ext:3.0'),
  @('3.3','Cargar documentación adicional',1,3,'ext:3.2'))
DibujoRequisitos $mod['Panel de administración'] 'Panel de administración' @(
  @('4.0','Consultar listado de postulaciones',0,1,'actor:Admin user'),
  @('4.1','Comparar datos y documentos',1,1,'ext:4.0'),
  @('4.2','Validar o rechazar postulación',1,0,'ext:4.1'),
  @('4.5','Solicitar documentación adicional',1,2,'ext:4.1'),
  @('4.3','Migrar información desde Excel',0,3,'actor:Admin user'),
  @('4.4','Registrar auditoría',0,4,'actor:Sistema user'))
# Cada aviso es un caso propio del Sistema; «enviar por correo» (RF-5.7) extiende a cada aviso.
DibujoRequisitos $mod['Notificaciones'] 'Notificaciones' @(
  @('5.0','Notificar validación',0,0,'actor:Sistema user'),
  @('5.1','Notificar rechazo',0,1,'actor:Sistema user'),
  @('5.3','Notificar aprobación',0,2,'actor:Sistema user'),
  @('5.4','Notificar pago',0,3,'actor:Sistema user'),
  @('5.5','Notificar novedad',0,4,'actor:Sistema user'),
  @('5.7','Enviar notificación por correo',1,2,'ext:5.0,5.1,5.3,5.4,5.5'),
  @('5.6','Consultar notificaciones',0,5,'actor:Aprendiz user'),
  @('5.2','Consultar estado de postulación',0,6,'actor:Aprendiz user')) 100
DibujoRequisitos $mod['Seguimiento de pagos'] 'Seguimiento de pagos' @(
  @('6.2','Consultar historial de pagos',0,1,'actor:Admin user'),
  @('6.0','Registrar pago',1,0,'ext:6.2'),
  @('6.3','Anular pago',1,2,'ext:6.2'),
  @('6.1','Finalizar beneficio automáticamente',0,3,'actor:Sistema user'),
  @('6.4','Consultar mis pagos',0,4,'actor:Aprendiz user'))
DibujoRequisitos $mod['Beneficios y convocatorias'] 'Beneficios y convocatorias' @(
  @('7.0','Gestionar beneficios',0,0,'actor:Admin user'),
  @('7.1','Crear y modificar convocatorias',0,1,'actor:Admin user'),
  @('7.2','Cambiar estado de convocatoria',1,1,'ext:7.1'),
  @('7.3','Consultar convocatorias abiertas',0,3,'actor:Aprendiz user'))
DibujoRequisitos $mod['Asignación de beneficios'] 'Asignación de beneficios' @(
  @('8.0','Aprobar postulación validada',0,0,'actor:Admin user'),
  @('8.1','Rechazar postulación validada',0,1,'actor:Admin user'),
  @('8.7','Consultar asignaciones de beneficio',0,2,'actor:Admin user'),
  @('8.3','Ajustar asignación',1,1,'ext:8.7'),
  @('8.4','Suspender asignación',1,2,'ext:8.7'),
  @('8.5','Reactivar asignación',1,3,'ext:8.7'),
  @('8.6','Cancelar asignación',1,4,'ext:8.7'),
  @('8.2','Crear asignación automáticamente',0,4,'actor:Sistema user'))
DibujoRequisitos $mod['Novedades'] 'Novedades' @(
  @('9.0','Registrar novedad',0,0,'actor:Admin user'),
  @('9.1','Registrar novedad automática',0,2,'actor:Sistema user'),
  @('9.2','Consultar novedades',0,4,'actor:Aprendiz user'))

# Limpieza: color de línea por defecto (como Alfasocial) y restos huérfanos heredados de la plantilla
$r.Execute("UPDATE t_connector SET LineColor=-1")
$r.Execute("DELETE FROM t_xref WHERE Client IN (SELECT ea_guid FROM t_connector WHERE Start_Object_ID=0 OR End_Object_ID=0)")
$r.Execute("DELETE FROM t_connector WHERE Start_Object_ID=0 OR End_Object_ID=0")
$r.Execute("DELETE FROM t_diagramlinks WHERE DiagramID=0")
"CONSTRUIDO"
