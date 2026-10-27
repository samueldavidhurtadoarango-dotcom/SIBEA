# Herramientas para regenerar los entregables

- `generar_ficha.js` crea `Ficha_Proyecto_BenefitRack_v2.docx`. Necesita `npm install docx@9.5.1`. Uso: `node generar_ficha.js <ruta_salida.docx>`.
- `generar_matriz.js` y `datos_matriz.js` crean `entregables/Matriz_Requisitos_v2.xlsx`. Necesitan `npm install exceljs@4.4.0`. Puede que haya que ajustar las rutas de entrada y salida al inicio del script.
- `entregables/verificar_matriz.js` comprueba la matriz contra `base-de-datos/benefitrack_v2.sql` y no necesita dependencias.

Instala las dependencias en una carpeta aparte, no en el repositorio.
