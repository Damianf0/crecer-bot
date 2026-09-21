# Fuentes de los manuales

Cada `manual-*.js` arma un .docx con `lib-manual.js` (portada, logo de WORKBENCH,
encabezado y pie comunes). El PDF se genera con LibreOffice en un contenedor
descartable, con fuente de emojis para que los íconos de los botones se vean:

```sh
npm install docx
node manual-administracion.js Manual-Area-Administracion-Crecer.docx
node manual-supervision.js Manual-Supervision-Crecer.docx
docker run --rm -v "$PWD":/w -w /w alpine:3.20 sh -c "apk add --no-cache -q libreoffice-writer font-dejavu font-crosextra-carlito font-noto-emoji; soffice --headless --convert-to pdf *.docx"
```

`logo/logo-workbench.png`: isotipo "Wi" redibujado como vector a partir de la
imagen original del bot de WORKBENCH (misma geometría, rojo de marca #e31e24),
más el wordmark del sitio (Inter ExtraBold, WORK negro / BENCH rojo / IT gris).
