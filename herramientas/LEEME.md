# Herramientas para regenerar los entregables

- `generar_ficha.js` crea `Ficha_Proyecto_BenefitRack_v2.docx`. Necesita `npm install docx@9.5.1`. Uso: `node generar_ficha.js <ruta_salida.docx>`.
- `generar_matriz.js` y `datos_matriz.js` crean la matriz de requisitos. Necesitan `npm install exceljs@4.4.0`.
  El contenido (requisitos y control de cambios) está en `datos_matriz.js`; `generar_matriz.js` solo le da formato.
  Uso: `node herramientas/generar_matriz.js <ruta_salida.xlsx>`. El único argumento es la ruta del Excel que se va
  a escribir (normalmente `entregables/Matriz_Requisitos_v2.xlsx`); si falta, el script se detiene con un mensaje
  de error y no escribe nada. La entrada (`datos_matriz.js`) se busca siempre junto al script, así que no hay que
  cambiar ninguna ruta dentro del código.
- `entregables/verificar_matriz.js` comprueba la matriz contra `base-de-datos/benefitrack_v2.sql` y no necesita dependencias.
  Uso: `node entregables/verificar_matriz.js [ruta_xlsx] [ruta_sql]` (sin argumentos revisa la v2 contra el SQL del repositorio).

Instala las dependencias en una carpeta aparte, no en el repositorio, y apúntala con `NODE_PATH`.

Ejemplo sin instalar nada dentro del repositorio (dependencias en otra carpeta):

```bash
NODE_PATH=/ruta/a/carpeta_deps/node_modules node herramientas/generar_matriz.js entregables/Matriz_Requisitos_v2.xlsx
node entregables/verificar_matriz.js
node entregables/verificar_matriz.js originales/Matriz_Requisitos.xlsx   # la original, para comparar
```

No edites el `.xlsx` a mano: cambia `datos_matriz.js` y vuelve a generarlo.

En `datos_matriz.js`, cada fila del control de cambios puede llevar una quinta posición con la fecha
(`'27/10/2026'`); si no la lleva, se usa `26/10/2026`.
