'use strict';

const http = require('node:http');
const net = require('node:net');
const { WebSocketServer, WebSocket } = require('ws');

const origins = new Set(['http://127.0.0.1', 'http://localhost']);
const server = http.createServer((_req, res) => {
  res.writeHead(404);
  res.end();
});
const wss = new WebSocketServer({ noServer: true, maxPayload: 1024 * 1024, perMessageDeflate: false });
const sockets = new Set();

server.on('upgrade', (req, socket, head) => {
  if (req.url !== '/game' || !origins.has(req.headers.origin) || socket.remoteAddress !== '127.0.0.1') {
    socket.end('HTTP/1.1 403 Forbidden\r\nConnection: close\r\nContent-Length: 0\r\n\r\n');
    return;
  }
  wss.handleUpgrade(req, socket, head, ws => wss.emit('connection', ws));
});

wss.on('connection', ws => {
  // The browser cannot choose a destination, port, or interface.
  const tcp = net.createConnection({ host: '127.0.0.1', port: 9200 });
  sockets.add(tcp);
  const close = () => {
    tcp.destroy();
    if (ws.readyState === WebSocket.OPEN) ws.close();
  };
  tcp.on('error', () => {
    if (ws.readyState === WebSocket.OPEN) ws.close(1011, 'TCP unavailable');
    tcp.destroy();
  });
  tcp.on('close', () => { sockets.delete(tcp); close(); });
  tcp.on('data', data => {
    if (ws.readyState !== WebSocket.OPEN) return;
    tcp.pause();
    ws.send(data, { binary: true }, error => {
      if (error) close();
      else tcp.resume();
    });
  });
  tcp.on('drain', () => ws.resume());
  ws.on('message', (data, isBinary) => {
    if (!isBinary) {
      ws.close(1003, 'Binary frames required');
      tcp.destroy();
      return;
    }
    if (!tcp.destroyed && !tcp.write(data)) ws.pause();
  });
  ws.on('error', close);
  ws.on('close', () => tcp.destroy());
});

server.on('error', error => {
  console.error(`Ruffle proxy: ${error.message}`);
  process.exitCode = 1;
});
server.listen(8765, '127.0.0.1', () => {
  console.log('Ruffle proxy: ws://127.0.0.1:8765/game -> 127.0.0.1:9200');
});

function shutdown() {
  for (const ws of wss.clients) ws.terminate();
  for (const tcp of sockets) tcp.destroy();
  wss.close();
  server.close();
}
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
