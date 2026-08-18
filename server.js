const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = process.env.PORT || 3000;
const ROOT = __dirname;
const DRIVE_ENDPOINT = 'https://drive.knnidiomas.com.br/api/v1/parceria-cupons/landingpage-cupom/';

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp'
};

function send(res, status, body, type = 'text/plain; charset=utf-8') {
  res.writeHead(status, {
    'Content-Type': type,
    'Cache-Control': 'no-store'
  });
  res.end(body);
}

function serveStatic(req, res) {
  const url = new URL(req.url, `http://${req.headers.host}`);
  let requestedPath = decodeURIComponent(url.pathname);
  if (requestedPath === '/') requestedPath = '/index.html';

  const filePath = path.normalize(path.join(ROOT, requestedPath));
  if (!filePath.startsWith(ROOT)) return send(res, 403, 'Forbidden');

  fs.readFile(filePath, (err, data) => {
    if (err) return send(res, 404, 'Not found');
    const type = MIME[path.extname(filePath).toLowerCase()] || 'application/octet-stream';
    res.writeHead(200, {
      'Content-Type': type,
      'Cache-Control': 'no-store'
    });
    res.end(data);
  });
}

async function proxyLead(req, res) {
  let body = '';
  req.on('data', chunk => {
    body += chunk;
    if (body.length > 100_000) req.destroy();
  });

  req.on('end', async () => {
    try {
      const input = JSON.parse(body || '{}');
      const payload = {
        cda_id: null,
        email: String(input.email || '').trim(),
        idade: String(input.idade || '').trim(),
        nome: String(input.nome || '').trim(),
        parceria_id: 37061,
        status_id: 1,
        telefone: String(input.telefone || '').trim()
      };

      if (!payload.nome || !payload.email || !payload.idade || !payload.telefone) {
        return send(res, 422, JSON.stringify({ error: 'Campos obrigatórios ausentes' }), 'application/json; charset=utf-8');
      }

      const driveResponse = await fetch(DRIVE_ENDPOINT, {
        method: 'POST',
        headers: {
          'Accept': 'application/json, text/plain, */*',
          'Content-Type': 'application/json;charset=UTF-8'
        },
        body: JSON.stringify(payload)
      });

      const responseText = await driveResponse.text();
      res.writeHead(driveResponse.status, {
        'Content-Type': driveResponse.headers.get('content-type') || 'application/json; charset=utf-8',
        'Cache-Control': 'no-store'
      });
      res.end(responseText);
    } catch (error) {
      console.error('[KNN] Erro no proxy DRIVE:', error);
      send(res, 502, JSON.stringify({ error: 'Falha ao comunicar com o DRIVE' }), 'application/json; charset=utf-8');
    }
  });
}

const server = http.createServer((req, res) => {
  if (req.method === 'POST' && req.url === '/api/lead') {
    return proxyLead(req, res);
  }

  if (req.method === 'GET' || req.method === 'HEAD') {
    return serveStatic(req, res);
  }

  return send(res, 405, 'Method not allowed');
});

server.listen(PORT, '0.0.0.0', () => {
  console.log(`KNN Barretos rodando em http://localhost:${PORT}`);
});
