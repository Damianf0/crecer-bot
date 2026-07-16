# Imagen bot WA con Chrome for Testing PINNEADO (delta workbench 16/07, D1).
# Reemplaza al chromium de Alpine: build comunitaria musl de version FLOTANTE
# (cada rebuild tomaba lo que Alpine shippeara ese dia — llego a 147, varias
# majors por delante de lo que puppeteer certifica). Aca: Debian glibc +
# Chrome for Testing 146.0.7680.31 — la version exacta que pinnea el
# puppeteer 24.38 del package-lock (misma logica que la instancia estable
# workbench-bot: browser fijo, certificado, que solo cambia a proposito).
FROM node:20-bookworm-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
      ca-certificates git tzdata wget \
      fonts-liberation libasound2 libatk-bridge2.0-0 libatk1.0-0 libcairo2 \
      libcups2 libdbus-1-3 libdrm2 libexpat1 libgbm1 libglib2.0-0 libnspr4 \
      libnss3 libpango-1.0-0 libx11-6 libxcb1 libxcomposite1 libxdamage1 \
      libxext6 libxfixes3 libxkbcommon0 libxrandr2 xdg-utils \
 && rm -rf /var/lib/apt/lists/* \
 && ln -sf /usr/share/zoneinfo/America/Argentina/Buenos_Aires /etc/localtime \
 && echo "America/Argentina/Buenos_Aires" > /etc/timezone

ENV TZ=America/Argentina/Buenos_Aires

# Chrome for Testing pinneado. Para actualizar: cambiar la version aca Y en
# CHROMIUM_PATH, siempre a la que diga node_modules/puppeteer-core/.../revisions.js.
RUN npx -y @puppeteer/browsers install chrome@146.0.7680.31 --path /opt/chrome \
 && ln -s /opt/chrome/chrome/linux-146.0.7680.31/chrome-linux64/chrome /usr/local/bin/chrome-pinned

ENV CHROMIUM_PATH=/usr/local/bin/chrome-pinned
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true

WORKDIR /app
CMD ["node", "index.js"]
