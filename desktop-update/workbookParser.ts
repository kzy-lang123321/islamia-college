export type WorkbookStudentRow = { registration: string | null; fullName: string; fatherName: string | null; phone: string | null; cnic: string | null; address: string | null; rollNumber: string | null; agreedFee: number | null; paidAmount: number | null; admissionDate: string | null; slipNumber: string | null };
export type WorkbookSheetPlan = { name: string; kind: 'students' | 'reference'; courseCode: string; courseName: string; batchCode: string; standardFee: number; students: WorkbookStudentRow[]; sourceRows: string[][]; detectionWarnings: string[]; headerRow: number };
const clean = (s: string) => s.toLowerCase().replace(/[^a-z0-9]/g, '');
const aliases = {
  name: ['name', 'studentname', 'namedate', 'nameofstudent'], father: ['fname', 'fathername', 'fathersname', 'khan'],
  reg: ['regno', 'registrationnumber', 'registrationno', 'regn', 'rgn', 'stdid', 'studentid', 'cno'],
  roll: ['rollno', 'rollnumber', 'rno'], phone: ['contact', 'contactno', 'contactnumber', 'phone', 'mobile', 'coontact'],
  address: ['address', 'addres', 'addess'], cnic: ['cnic', 'cnicno', 'bform'], date: ['dateofadmi', 'dateofadmission', 'admissiondate', 'date'],
  fee: ['totalfee', 'agreedfee', 'fee', 'tuitionfee'], paid: ['totalpaid', 'tpaid', 'feepaid', 'paid'], dues: ['totaldues', 'dues'],
  slip: ['slipno', 'receiptno', 'recieptno', 'paidno']
};
const find = (headers: string[], names: string[]) => { for (const n of names) { const i = headers.indexOf(n); if (i >= 0) return i; } return -1; };
const amount = (v: string | undefined) => { const text = (v || '').replace(/,/g, '').replace(/^Rs\.?\s*/i, '').trim(); return /^-?\d+(\.\d+)?$/.test(text) ? Number(text) : null; };
export function parseWorkbookSheets(X: any, book: any): WorkbookSheetPlan[] {
  return book.SheetNames.map((name: string) => {
    const sheet = book.Sheets[name];
    let sourceRows: string[][] = [];
    if (sheet['!ref']) {
      const range = X.utils.decode_range(sheet['!ref']); range.s.r = 0; range.s.c = 0; range.e.r = Math.min(range.e.r, 20000); range.e.c = Math.min(range.e.c, 99);
      sourceRows = X.utils.sheet_to_json(sheet, { header: 1, raw: false, defval: '', range }).map((r: any[]) => r.map(v => String(v ?? '')));
      while (sourceRows.length && sourceRows.at(-1)!.every(cell => !cell.trim())) sourceRows.pop();
    }
    let headerRow = -1, bestScore = 0;
    sourceRows.slice(0, 30).forEach((r, index) => {
      const h = r.map(clean); if (find(h, aliases.name) < 0) return;
      const score = 5 + (find(h, aliases.reg) >= 0 ? 4 : 0) + (find(h, aliases.roll) >= 0 ? 2 : 0) + (find(h, aliases.father) >= 0 ? 2 : 0) + (find(h, aliases.phone) >= 0 ? 2 : 0) + (find(h, aliases.fee) >= 0 ? 1 : 0);
      if (score > bestScore) { headerRow = index; bestScore = score; }
    });
    const courseCode = name.trim().toUpperCase().match(/^(?:ICT[- _])?(DIT|ELL|CIT|AI|VE|PTE|OXFORD|SAFETY|TUITION)/)?.[1] || name.trim().toUpperCase().replace(/[^A-Z]/g, '').slice(0, 12) || 'LEGACY';
    const courseNames: Record<string, string> = { DIT: 'Diploma in Information Technology', ELL: 'English Language Learning', CIT: 'Certificate in Information Technology', AI: 'Artificial Intelligence', VE: 'Video Editing', PTE: 'PTE / Oxford', SAFETY: 'Safety Courses', TUITION: 'Tuition' };
    const reference = /TTB|RESULT|POSITION|STAFF|RENT|QARZY|LOAN|SOLAR|COURSES STUDENTS|^COURSES$|^SHEET\d+$/i.test(name);
    const kind = !reference && headerRow >= 0 && bestScore >= 9 ? 'students' : 'reference';
    const warnings: string[] = [];
    const students: WorkbookStudentRow[] = [];
    if (headerRow >= 0) {
      const h = sourceRows[headerRow].map(clean);
      const at = Object.fromEntries(Object.entries(aliases).map(([key, values]) => [key, find(h, values)])) as Record<keyof typeof aliases, number>;
      const semesterFee = h.map((v, i) => /^(sem|s|semester)[12]fee$/.test(v) ? i : -1).filter(i => i >= 0);
      const semesterPaid = h.map((v, i) => /^(sem|s|semester)[12]paid$/.test(v) ? i : -1).filter(i => i >= 0);
      for (const row of sourceRows.slice(headerRow + 1)) {
        const fullName = row[at.name]?.trim() || '';
        if (!fullName || !/\p{L}/u.test(fullName) || /^(total|grand total|balance|cash|paid|dues|name|name \/date|remaining)(\s|$)/i.test(fullName)) continue;
        const cell = (field: keyof typeof aliases) => row[at[field]]?.trim() || null;
        const sum = (indices: number[]) => { const values = indices.map(i => amount(row[i])).filter((v): v is number => v !== null); return values.length ? values.reduce((a, b) => a + b, 0) : null; };
        const agreedFee = amount(row[at.fee]) ?? sum(semesterFee);
        const paidAmount = amount(row[at.paid]) ?? sum(semesterPaid);
        const sourceDues = amount(row[at.dues]);
        if (sourceDues !== null && agreedFee !== null && paidAmount !== null && Math.abs(sourceDues - (agreedFee - paidAmount)) > .01 && warnings.length < 20)
          warnings.push(`${fullName}: source dues ${sourceDues} differ from recognized fee ${agreedFee} minus paid ${paidAmount}. Check this opening balance.`);
        students.push({ registration: cell('reg') ?? (courseCode === 'ELL' ? cell('roll') : null), fullName, fatherName: cell('father'), phone: cell('phone'), cnic: cell('cnic'), address: cell('address'), rollNumber: cell('roll') ?? cell('reg'), agreedFee, paidAmount, admissionDate: cell('date'), slipNumber: cell('slip') });
      }
    }
    const frequencies = new Map<number, number>();
    students.forEach(s => { if (s.agreedFee !== null && s.agreedFee >= 0) frequencies.set(s.agreedFee, (frequencies.get(s.agreedFee) || 0) + 1); });
    const standardFee = [...frequencies.entries()].sort((a, b) => b[1] - a[1])[0]?.[0] ?? 0;
    return { name, kind, courseCode, courseName: courseNames[courseCode] || name.trim(), batchCode: name.trim().toUpperCase().replace(/[^A-Z0-9-]+/g, '-').replace(/^-|-$/g, ''), standardFee, students, sourceRows, detectionWarnings: warnings, headerRow };
  });
}
