# Herramientas para regenerar los entregables

- `generar_ficha.js` crea `Ficha_Proyecto_BenefitRack_v2.docx`. Necesita `npm install docx@9.5.1`. Uso: `node generar_ficha.js <ruta_salida.docx>`.
- `generar_matriz.js` y `datos_matriz.js` crean `entregables/Matriz_Requisitos_v2.xlsx`. Necesitan `npm install exceljs@4.4.0`. Puede que haya que ajustar las rutas de entrada y salida al inicio del script.
- `entregables/verificar_matriz.js` comprueba la matriz contra `base-de-datos/benefitrack_v2.sql` y no necesita dependencias.

Instala las dependencias en una carpeta aparte, no en el repositorio.

Ejemplo sin instalar nada dentro del repositorio (dependencias en otra carpeta):

```bash
NODE_PATH=/ruta/a/carpeta_deps/node_modules node herramientas/generar_matriz.js entregables/Matriz_Requisitos_v2.xlsx
node entregables/verificar_matriz.js
```

En `datos_matriz.js`, cada fila del control de cambios puede llevar una quinta posición con la fecha
(`'27/10/2026'`); si no la lleva, se usa `26/10/2026`.
