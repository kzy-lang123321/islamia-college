const fs = require('fs');
const path = require('path');
const dir = 'C:\\Ameen Consultancy';
const htmlFiles = fs.readdirSync(dir).filter(f => f.endsWith('.html'));

let allOk = true;
htmlFiles.forEach(file => {
  const content = fs.readFileSync(path.join(dir, file), 'utf8');
  const imgRegex = /src=["']([^"']+)["']/g;
  let m;
  while ((m = imgRegex.exec(content)) !== null) {
    const imgSrc = m[1];
    const fullPath = path.resolve(dir, imgSrc);
    if (!fs.existsSync(fullPath)) {
      console.log('BROKEN IMAGE in ' + file + ': ' + imgSrc);
      allOk = false;
    }
  }
});
if (allOk) {
  console.log('SUCCESS: Every image referenced across all 15 pages exists on disk!');
}
