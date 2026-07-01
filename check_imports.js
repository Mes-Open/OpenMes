const fs = require('fs');
const path = require('path');

function walk(d) {
    let res = [];
    fs.readdirSync(d).forEach(f => {
        let p = path.join(d, f);
        if (fs.statSync(p).isDirectory()) {
            res = res.concat(walk(p));
        } else if (p.endsWith('.jsx')) {
            res.push(p);
        }
    });
    return res;
}

let bad = [];
walk('backend/resources/js').forEach(f => {
    let c = fs.readFileSync(f, 'utf8');
    let m = c.match(/import\s+\{\s*__\s*\}\s+from\s+['"]([^'"]+)['"]/);
    if (m) {
        let target = path.resolve(path.dirname(f), m[1] + (m[1].endsWith('.js') ? '' : '.js'));
        if (!fs.existsSync(target)) {
            bad.push({file: f, target: target});
        }
    }
});

console.log('Bad imports:', bad);
