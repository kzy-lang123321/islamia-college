const fs = require('fs');
const pdfParse = require('./node_modules/pdf-parse');

const dataBuffer = fs.readFileSync('C:\\Users\\irfan\\Downloads\\AMEEN CONSULTANCY (PVT)LTD.pdf');

pdfParse(dataBuffer).then(function(data) {
    console.log('=== PAGES:', data.numpages, '===');
    console.log(data.text);
}).catch(err => {
    console.error('Error:', err.message);
});
