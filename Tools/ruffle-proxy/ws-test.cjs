'use strict';
const WebSocket = require('ws');
const os = require('node:os');
function lanIp() {
  for (const addrs of Object.values(os.networkInterfaces())) {
    for (const a of addrs || []) {
      if (a.family === 'IPv4' && !a.internal) return a.address;
    }
  }
  return '127.0.0.1';
}
const LAN = process.env.DDTANK_BASE ? new URL(process.env.DDTANK_BASE).hostname : lanIp();
const cases = [
  { url: `ws://${LAN}:8765/game`, origin: `http://${LAN}` },
  { url: 'ws://127.0.0.1:8765/game', origin: 'http://127.0.0.1' },
  { url: `ws://${LAN}:8765/game`, origin: 'http://evil.example' },
  { url: `ws://${LAN}:8765/other`, origin: `http://${LAN}` },
];
(async () => {
  for (const t of cases) {
    await new Promise(resolve => {
      let settled = false;
      const done = msg => { if (!settled) { settled = true; console.log(`${t.origin} @ ${t.url} => ${msg}`); try { ws.terminate(); } catch {} resolve(); } };
      const ws = new WebSocket(t.url, { headers: { Origin: t.origin } });
      ws.on('open', () => done('OPEN'));
      ws.on('unexpected-response', (_r, res) => done('HTTP ' + res.statusCode));
      ws.on('error', e => done('ERROR ' + e.message));
      setTimeout(() => done('TIMEOUT'), 5000);
    });
  }
})();
