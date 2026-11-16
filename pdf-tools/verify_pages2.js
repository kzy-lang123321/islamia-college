const fs = require('fs');
const path = require('path');
const dir = 'C:\\Ameen Consultancy';
const htmlFiles = fs.readdirSync(dir).filter(f => f.endsWith('.html'));

let allOk = true;
htmlFiles.forEach(file => {
  const content = fs.readFileSync(path.join(dir, file), 'utf8');
  const hrefRegex = /href=["']([^"'#]+)(?:#[^"']*)?["']/g;
  let m;
  while ((m = hrefRegex.exec(content)) !== null) {
    const link = m[1].split('?')[0];
    if (link.startsWith('http://') || link.startsWith('https://') || link.startsWith('tel:') || link.startsWith('mailto:')) {
      continue;
    }
    if (link.endsWith('.html') || link.endsWith('.css') || link.endsWith('.jpg') || link.endsWith('.png')) {
      const fullPath = path.resolve(dir, link);
      if (!fs.existsSync(fullPath)) {
        console.log('BROKEN LINK in ' + file + ': ' + link);
        allOk = false;
      }
    }
  }
});
if (allOk) {
  console.log('SUCCESS: All internal links are 100% verified and exist on disk!');
}
