'use strict';

const http = require('node:http');
const net = require('node:net');
const os = require('node:os');
const { WebSocketServer, WebSocket } = require('ws');

// Destination is fixed: clients can never pick a host, port or interface.
const DEST_HOST = '127.0.0.1';
const DEST_PORT = 9200;

function ip4ToInt(ip) {
  const parts = ip.split('.').map(Number);
  if (parts.length !== 4 || parts.some(n => !Number.isInteger(n) || n < 0 || n > 255)) return -1;
  return ((parts[0] << 24) | (parts[1] << 16) | (parts[2] << 8) | parts[3]) >>> 0;
}
const LOOPBACK_START = ip4ToInt('127.0.0.0');
const LOOPBACK_END = ip4ToInt('127.255.255.255');

// Allowed peers: loopback + every IPv4 subnet this machine currently owns
// (Wi-Fi/LAN adapters). Rebuilt at startup, so DHCP IP changes just work.
// DDTANK_PROXY_ALLOW can add extra IPs/CIDRs (comma-separated) when needed.
function buildAllowedRanges() {
  const ranges = [{ start: LOOPBACK_START, end: LOOPBACK_END, label: 'loopback' }];
  for (const addrs of Object.values(os.networkInterfaces())) {
    for (const a of addrs || []) {
      if (a.family !== 'IPv4' || a.internal) continue;
      const ipInt = ip4ToInt(a.address);
      if (ipInt < 0) continue;
      const prefixRaw = a.cidr ? Number(String(a.cidr).split('/')[1]) : NaN;
      const prefix = Number.isInteger(prefixRaw) && prefixRaw >= 8 && prefixRaw <= 32 ? prefixRaw : 24;
      const mask = prefix >= 32 ? 0xffffffff : ((~0 << (32 - prefix)) >>> 0);
      const start = (ipInt & mask) >>> 0;
      ranges.push({ start, end: (start | (~mask >>> 0)) >>> 0, label: a.address + '/' + prefix });
    }
  }
  for (const item of String(process.env.DDTANK_PROXY_ALLOW || '').split(',')) {
    const s = item.trim();
    if (!s) continue;
    const [ip, pRaw] = s.split('/');
    const ipInt = ip4ToInt(ip);
    if (ipInt < 0) continue;
    const prefixRaw = pRaw ? Number(pRaw) : 32;
    const prefix = Number.isInteger(prefixRaw) && prefixRaw >= 8 && prefixRaw <= 32 ? prefixRaw : 32;
    const mask = prefix >= 32 ? 0xffffffff : ((~0 << (32 - prefix)) >>> 0);
    const start = (ipInt & mask) >>> 0;
    ranges.push({ start, end: (start | (~mask >>> 0)) >>> 0, label: s + ' (env)' });
  }
  return ranges;
}
const ALLOWED_RANGES = buildAllowedRanges();

function isLocalV4(ip) {
  const n = ip4ToInt(ip);
  if (n < 0) return false;
  return ALLOWED_RANGES.some(r => n >= r.start && n <= r.end);
}

function peerAddress(raw) {
  if (!raw) return '';
  return raw.startsWith('::ffff:') ? raw.slice(7) : raw;
}

// Loopback (PC) and the home LAN only. Nothing else can reach this port.
function isAllowedPeer(raw) {
  const ip = peerAddress(raw);
  return ip === '::1' || isLocalV4(ip);
}

function isAllowedOrigin(origin) {
  if (!origin) return false;
  let url;
  try { url = new URL(origin); } catch { return false; }
  if (url.protocol !== 'http:') return false;
  const host = url.hostname.toLowerCase();
  if (host === 'localhost') return true;
  return isLocalV4(host);
}

const server = http.createServer((_req, res) => {
  res.writeHead(404);
  res.end();
});
const wss = new WebSocketServer({ noServer: true, maxPayload: 1024 * 1024, perMessageDeflate: false });
const sockets = new Set();

server.on('upgrade', (req, socket, head) => {
  if (req.url !== '/game' || !isAllowedOrigin(req.headers.origin) || !isAllowedPeer(socket.remoteAddress)) {
    socket.end('HTTP/1.1 403 Forbidden\r\nConnection: close\r\nContent-Length: 0\r\n\r\n');
    return;
  }
  wss.handleUpgrade(req, socket, head, ws => wss.emit('connection', ws));
});

wss.on('connection', ws => {
  const tcp = net.createConnection({ host: DEST_HOST, port: DEST_PORT });
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
// Listen on every interface: loopback for the PC, LAN for the phone.
server.listen(8765, () => {
  const allowed = ALLOWED_RANGES.map(r => r.label).join(', ');
  console.log(`Ruffle proxy: ws://<host>:8765/game -> ${DEST_HOST}:${DEST_PORT} (allowed: ${allowed})`);
});

function shutdown() {
  for (const ws of wss.clients) ws.terminate();
  for (const tcp of sockets) tcp.destroy();
  wss.close();
  server.close();
}
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
