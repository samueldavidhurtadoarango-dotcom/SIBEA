# Receta del diagrama de casos de uso

El diagrama `documentacion/entregables/SIBEA_CasosDeUso.eap` no se dibujó a mano: se **cocina con
scripts**, como una receta. Si alguien borra el archivo o hay que cambiarlo, estos scripts lo
vuelven a hacer igual cada vez y comprueban que quedó bien.

## Qué es cada script

| Script | Para qué sirve (la analogía) |
|---|---|
| `construir.ps1` | **La receta completa.** Toma un molde (la plantilla Alfasocial), le vacía el contenido y dibuja todo el diagrama. Al final lo compacta (le quita lo que sobra, como sacar el aire de una bolsa). |
| `parte2.ps1` | **La lista de platos.** Contiene los dibujos de cada módulo (óvalos, actores, flechas). `construir.ps1` lo llama solo; no se ejecuta suelto. |
| `verificar.ps1` | **El inspector de calidad.** Abre el `.eap` sin tocarlo y hace 61 chequeos contra la matriz de requisitos (nombres, actores, conexiones, que no queden restos de Alfasocial). Debe terminar con `61 OK / 0 FALLA`. |
| `exportar.ps1` | **La cámara.** Saca una imagen PNG de cada dibujo del `.eap`. |

## Qué se necesita

1. **Enterprise Architect 8** instalado (los scripts le hablan por su API COM).
2. **PowerShell de 32 bits** (Enterprise Architect 8 y el motor de Access/Jet son de 32 bits).
   Se usa siempre así:
   `C:/Windows/SysWOW64/WindowsPowerShell/v1.0/powershell.exe -ExecutionPolicy Bypass -File <script> ...`
3. La **plantilla Alfasocial** (`alfa.eap`): es un archivo de otro proyecto, por eso **no está en
   este repositorio**. Solo la necesita `construir.ps1`.

Los comandos se ejecutan desde esta carpeta (`documentacion/herramientas/diagrama`).

## 1. Revisar el diagrama actual

```
C:/Windows/SysWOW64/WindowsPowerShell/v1.0/powershell.exe -ExecutionPolicy Bypass -File verificar.ps1
```

Sin parámetros revisa `documentacion/entregables/SIBEA_CasosDeUso.eap`. Para revisar otro archivo:
agregar `-Archivo ruta\al\otro.eap`. El resultado esperado es la línea final
`RESULTADO: 61 OK / 0 FALLA`.

## 2. Rehacer el diagrama

```
C:/Windows/SysWOW64/WindowsPowerShell/v1.0/powershell.exe -ExecutionPolicy Bypass -File construir.ps1 -Plantilla "C:\ruta\a\alfa.eap" -Salida "C:\ruta\a\prueba.eap"
```

Termina mostrando `CONSTRUIDO` y `COMPACTADO=True`. Después, comprobarlo:

```
C:/Windows/SysWOW64/WindowsPowerShell/v1.0/powershell.exe -ExecutionPolicy Bypass -File verificar.ps1 -Archivo "C:\ruta\a\prueba.eap"
```

> **Cuidado:** `construir.ps1` **sobrescribe** el archivo de `-Salida` si ya existe. Por eso conviene
> escribir en **otro archivo** (como `prueba.eap`), revisarlo con `verificar.ps1` y abrirlo en
> Enterprise Architect, y **solo si todo está bien** copiarlo encima de
> `documentacion/entregables/SIBEA_CasosDeUso.eap`.

## 3. Exportar las imágenes

```
C:/Windows/SysWOW64/WindowsPowerShell/v1.0/powershell.exe -ExecutionPolicy Bypass -File exportar.ps1 -Carpeta "C:\ruta\a\imagenes"
```

Crea la carpeta si no existe y guarda un PNG por dibujo. También acepta `-Archivo` para exportar
otro `.eap`.

## Si algo falla

- Si dice que no puede crear `EA.Repository`: Enterprise Architect no está instalado o se abrió
  PowerShell de 64 bits.
- Si `construir.ps1` dice `COMPACTAR falló`, el archivo igual es válido; solo queda un poco más grande.
