const fs = require("node:fs");
const http = require("node:http");
const path = require("node:path");

const assets = "C:\\Users\\S\\AppData\\Local\\Temp\\opencode\\swagger-editor-local\\node_modules\\swagger-editor-dist";
const spec = "C:\\xampp1\\htdocs\\tel-bot\\openapi.yaml";

http.createServer((request, response) => {
    const pathname = decodeURIComponent(new URL(request.url, "http://localhost").pathname);

    if (pathname === "/") {
        response.writeHead(200, { "Content-Type": "text/html; charset=utf-8" });
        response.end(`<!doctype html><html><head><meta charset="utf-8"><title>Swagger Editor — Famo API</title><link rel="stylesheet" href="/swagger-editor.css"></head><body><div id="swagger-editor"></div><script src="/swagger-editor-bundle.js"></script><script src="/swagger-editor-standalone-preset.js"></script><script>window.onload=function(){window.editor=SwaggerEditorBundle({dom_id:"#swagger-editor",layout:"StandaloneLayout",presets:[SwaggerEditorStandalonePreset],url:"/openapi.yaml"})}</script></body></html>`);
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
}).listen(8080, "127.0.0.1");
