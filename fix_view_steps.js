const fs = require('fs');
let code = fs.readFileSync('tests/e2e/car-production-buildout-vi.spec.ts', 'utf8');

code = code.replace(/\/View Steps\/i/g, '/(View Steps|Xem các bước)/i');

fs.writeFileSync('tests/e2e/car-production-buildout-vi.spec.ts', code);
console.log('Fixed View Steps');
