const fs = require("node:fs");
const http = require("node:http");
const path = require("node:path");

const root = __dirname;
const assets = path.join(root, "node_modules", "swagger-editor-dist");
const spec = path.join(root, "openapi.yaml");
const port = Number(process.env.SWAGGER_EDITOR_PORT || 8080);

if (!fs.existsSync(assets)) {
    console.error(`[swagger-editor] Missing "${assets}". Run "npm install" (or "npm.cmd install") in the project root first.`);
    process.exit(1);
}

http.createServer((request, response) => {
    const pathname = decodeURIComponent(new URL(request.url, "http://localhost").pathname);

    if (pathname === "/") {
        response.writeHead(200, { "Content-Type": "text/html; charset=utf-8" });
        response.end(`<!doctype html><html><head><meta charset="utf-8"><title>Swagger Editor \u2014 Famo API</title><link rel="stylesheet" href="/swagger-editor.css"></head><body><div id="swagger-editor"></div><script src="/swagger-editor-bundle.js"></script><script src="/swagger-editor-standalone-preset.js"></script><script>window.onload=function(){window.editor=SwaggerEditorBundle({dom_id:"#swagger-editor",layout:"StandaloneLayout",presets:[SwaggerEditorStandalonePreset],url:"/openapi.yaml"})}</script></body></html>`);
        return;
    }

    const filename = pathname === "/openapi.yaml" ? spec : path.resolve(assets, `.${pathname}`);
    if (pathname !== "/openapi.yaml" && !filename.startsWith(path.resolve(assets) + path.sep)) {
        response.writeHead(403).end("Forbidden");
        return;
    }

    fs.stat(filename, (error, stat) => {
        if (error || !stat.isFile()) {
            response.writeHead(404).end("Not found");
            return;
        }
        response.writeHead(200, { "Content-Type": filename.endsWith(".css") ? "text/css" : filename.endsWith(".js") ? "application/javascript" : filename.endsWith(".yaml") ? "application/yaml" : "application/octet-stream" });
        fs.createReadStream(filename).pipe(response);
    });
}).listen(port, "127.0.0.1", () => console.log(`Swagger Editor: http://127.0.0.1:${port}/`));
