const fs = require('fs');
const path = require('path');

const pdfPath = 'C:\\Users\\irfan\\Downloads\\AMEEN CONSULTANCY (PVT)LTD.pdf';
const outDir = 'C:\\Ameen Consultancy\\assets\\images';

if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });

const buf = fs.readFileSync(pdfPath);
const str = buf.toString('binary');

// Find all JPEG images (SOI marker FF D8 FF)
let count = 0;
let pos = 0;
while ((pos = str.indexOf('\xff\xd8\xff', pos)) !== -1) {
  // Find EOI marker FF D9
  let end = str.indexOf('\xff\xd9', pos + 3);
  if (end === -1) { pos++; continue; }
  end += 2;
  const len = end - pos;
  if (len > 5000) { // Only save if reasonably sized
    const imgBuf = buf.slice(pos, end);
    const outFile = path.join(outDir, image_.jpg);
    fs.writeFileSync(outFile, imgBuf);
    console.log(Saved:  ( KB));
  }
  pos = end;
}
console.log(\nTotal JPEG images found: );
