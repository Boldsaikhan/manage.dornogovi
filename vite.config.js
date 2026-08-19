import fs from 'fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

// Local dev over https://manage.dornogovi.gov.mn — reuse the Apache self-signed cert
// so the dev server is same-scheme as the app and the browser does not block assets.
const certPath = 'C:/xampp/apache/conf/ssl.crt/manage.dornogovi.gov.mn.crt';
const keyPath = 'C:/xampp/apache/conf/ssl.key/manage.dornogovi.gov.mn.key';
const hasCert = fs.existsSync(certPath) && fs.existsSync(keyPath);

export default defineConfig({
    server: {
        host: 'manage.dornogovi.gov.mn',
        ...(hasCert
            ? {
                  https: {
                      cert: fs.readFileSync(certPath),
                      key: fs.readFileSync(keyPath),
                  },
              }
            : {}),
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
