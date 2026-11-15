# 004 — Diagrama de casos de uso (Enterprise Architect)

## Objetivo
Entregar el diagrama de casos de uso de BenefitRack para la entrega del 4.º trimestre,
en un archivo de Enterprise Architect (`.eap`), hecho con el mismo molde que el
proyecto anterior del equipo (`alfasocialdiagrama.EAP`), que el instructor ya aceptó.

## Historia de usuario
Como aprendiz que sustenta BenefitRack, quiero un diagrama de casos de uso que siga la
matriz de requisitos v2 y el estilo de Alfasocial, para entregarlo y poder explicarlo.

## Criterios de aceptación
- **CA-1** Dado el archivo `documentacion/entregables/SIBEA_CasosDeUso.eap`, cuando
  se abre en Enterprise Architect 8, entonces abre sin errores.
- **CA-2** Dada la matriz v2 (46 requisitos, RF-1.0 a RF-9.2), cuando se cuentan los óvalos
  con nombre «RF-x.y …», entonces hay exactamente uno por cada requisito, con el mismo número.
- **CA-3** Dado el dibujo «Casos de uso principales», entonces muestra al actor Admin y un óvalo
  por módulo de la matriz (8), dentro del recuadro «SIBEA», y cada óvalo de módulo
  abre su propio dibujo (óvalo compuesto, icono ∞).
- **CA-4** Dado «Gestión de usuarios», entonces tiene el paso intermedio de Alfasocial:
  «Administración de usuarios» (RF-1) y «Control de acceso» (RF-2), cada uno con su dibujo.
- **CA-5** Dado un dibujo de requisitos, entonces los actores van a la izquierda, el recuadro
  con el nombre del módulo a la derecha, un óvalo central unido al actor con línea sólida y los
  demás unidos al central con flechas punteadas «extend»; el Admin user lanza flechas punteadas
  (dependencia) hacia los otros actores del dibujo.
- **CA-6** Todos los elementos y dibujos tienen autor «SENA», versión 1.0 y fase 1.0; los
  recuadros, estado «Propuesto»; los demás, estado vacío; colores por defecto; hoja A4 vertical.
- **CA-7** En el Project Browser, cada actor, recuadro, óvalo y dibujo queda guardado dentro del
  óvalo de su módulo, y cada módulo tiene su propia copia de los actores
  («Admin user», «Aprendiz user», «Sistema user»).

## Fuera de alcance
- Fichas escritas de cada caso de uso (el instructor solo pide el dibujo).
- Modificar los archivos `benefitrack.eap` y `benefitrack (1).eap` del Drive.
- Subir el archivo al Drive (lo decide Samuel).

## Decisiones (de Samuel)
- El sistema se llama SIBEA (Sistema de Información de Beneficios y Apoyos) desde el 2026-11-15; el recuadro y el archivo usan ese nombre.
- Formato `.eap`, igual a Alfasocial.
- Actores «Admin user», «Aprendiz user» y «Sistema user» dentro de los módulos; «Admin» en el nivel 1.
- Se copian las flechas punteadas entre actores, aunque no son UML estricto, porque el instructor
  ya las aceptó en Alfasocial.

## Decisiones (de Claude, a revisar por Samuel)
- El recuadro «SIBEA» del nivel 1 encierra los 8 módulos (en Alfasocial solo encerraba uno, por error).
- No se copian los dibujos repetidos de borrador de Alfasocial.
- No se usan óvalos «intermedios» con el mismo nombre del dibujo (como «Admin user» dentro de
  «Admin user» en Alfasocial): el actor se une directo al óvalo central, como en la mayoría
  de los dibujos de Alfasocial.
- Se usan tildes (Enterprise Architect las muestra bien).

## Casos borde
- Nombres largos: el óvalo crece para que el texto quepa.
- Módulos sin Admin (Notificaciones): no llevan flechas entre actores.
