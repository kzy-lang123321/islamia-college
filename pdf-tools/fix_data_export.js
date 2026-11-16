const fs = require('fs');
const content = fs.readFileSync('C:\\Ameen Consultancy\\pdf-tools\\build_data.js', 'utf8');
if (!content.includes('module.exports')) {
  fs.appendFileSync('C:\\Ameen Consultancy\\pdf-tools\\build_data.js', '\nmodule.exports = { destinations, services, whyPoints, brand };\n');
  console.log('Appended exports to build_data.js');
}
