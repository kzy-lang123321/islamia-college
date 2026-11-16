const PDFParser = require('./node_modules/pdf2json');

const pdfParser = new PDFParser();

pdfParser.on('pdfParser_dataError', errData => console.error('Error:', errData.parserError));

pdfParser.on('pdfParser_dataReady', pdfData => {
    const pages = pdfData.Pages || [];
    // Print pages 3-13 (middle pages)
    pages.slice(2, 13).forEach((page, idx) => {
        console.log(`\n=== PAGE ${idx + 3} ===`);
        const texts = page.Texts || [];
        let line = '';
        texts.forEach(t => {
            try {
                const txt = t.R.map(r => {
                    try { return decodeURIComponent(r.T); } 
                    catch(e) { return r.T || ''; }
                }).join('');
                line += txt + ' ';
            } catch(e) {}
        });
        console.log(line);
    });
});

pdfParser.loadPDF('C:\\Users\\irfan\\Downloads\\AMEEN CONSULTANCY (PVT)LTD.pdf');
