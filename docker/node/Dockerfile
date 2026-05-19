FROM node:20-alpine
WORKDIR /app
RUN apk add --no-cache chromium tzdata git \
 && cp /usr/share/zoneinfo/America/Argentina/Buenos_Aires /etc/localtime \
 && echo "America/Argentina/Buenos_Aires" > /etc/timezone
ENV TZ=America/Argentina/Buenos_Aires
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser
ENV CHROMIUM_PATH=/usr/bin/chromium-browser
CMD ["node", "index.js"]
