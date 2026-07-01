const fs = require('fs');
const lines = fs.readFileSync('tests/e2e/car-production-buildout-vi.spec.ts', 'utf8').split('\n');
lines.forEach((l, i) => {
  if (l.includes('getByRole')) {
    console.log(i + 1, l.trim());
  }
});
