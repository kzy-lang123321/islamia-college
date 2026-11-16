const PDFParser = require('./node_modules/pdf2json');

const pdfParser = new PDFParser();

pdfParser.on('pdfParser_dataError', errData => console.error('Error:', errData.parserError));

pdfParser.on('pdfParser_dataReady', pdfData => {
    // Extract all text from all pages
    const pages = pdfData.Pages || [];
    pages.forEach((page, idx) => {
        console.log(`\n=== PAGE ${idx + 1} ===`);
        const texts = page.Texts || [];
        texts.forEach(t => {
            const txt = t.R.map(r => decodeURIComponent(r.T)).join('');
            process.stdout.write(txt + ' ');
        });
        console.log('');
    });
});

pdfParser.loadPDF('C:\\Users\\irfan\\Downloads\\AMEEN CONSULTANCY (PVT)LTD.pdf');
