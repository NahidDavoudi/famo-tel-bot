const fs = require("node:fs");
const http = require("node:http");
const path = require("node:path");

const assets = "C:\\Users\\S\\AppData\\Local\\Temp\\opencode\\swagger-ui-local\\node_modules\\swagger-ui-dist";
const spec = "C:\\xampp1\\htdocs\\tel-bot\\openapi.yaml";

http.createServer((request, response) => {
    const pathname = decodeURIComponent(new URL(request.url, "http://localhost").pathname);

    if (pathname === "/") {
        response.writeHead(200, { "Content-Type": "text/html; charset=utf-8" });
        response.end('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Famo API — Swagger UI</title><link rel="stylesheet" href="/swagger-ui.css"><link rel="stylesheet" href="/index.css"><link rel="icon" href="/favicon-32x32.png"></head><body><div id="swagger-ui"></div><script src="/swagger-ui-bundle.js"></script><script src="/swagger-ui-standalone-preset.js"></script><script>window.onload=function(){window.ui=SwaggerUIBundle({url:"/openapi.yaml",dom_id:"#swagger-ui",deepLinking:true,presets:[SwaggerUIBundle.presets.apis,SwaggerUIStandalonePreset],plugins:[SwaggerUIBundle.plugins.DownloadUrl],layout:"StandaloneLayout"})}</script></body></html>');
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
        const ext = path.extname(filename);
        const mime = ext === ".css" ? "text/css" : ext === ".js" ? "application/javascript" : ext === ".yaml" ? "application/yaml" : ext === ".png" ? "image/png" : "application/octet-stream";
        response.writeHead(200, { "Content-Type": mime });
        fs.createReadStream(filename).pipe(response);
    });
}).listen(8081, "127.0.0.1", () => console.log("Swagger UI: http://127.0.0.1:8081/"));