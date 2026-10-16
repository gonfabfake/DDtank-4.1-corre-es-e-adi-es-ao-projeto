'use strict';
const WebSocket = require('ws');
const cases = [
  { url: 'ws://192.168.3.58:8765/game', origin: 'http://192.168.3.58' },
  { url: 'ws://127.0.0.1:8765/game', origin: 'http://127.0.0.1' },
  { url: 'ws://192.168.3.58:8765/game', origin: 'http://evil.example' },
  { url: 'ws://192.168.3.58:8765/other', origin: 'http://192.168.3.58' },
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
