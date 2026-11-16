export function formatPhone(value: string): string {
  let digits = value.replace(/\D/g, '');
  if (digits.startsWith('0092')) digits = digits.slice(4);
  else if (digits.startsWith('92')) digits = digits.slice(2);
  else if (digits.startsWith('0')) digits = digits.slice(1);
  return digits ? '+92-' + digits.slice(0, 10) : '';
}
export function formatCnic(value: string): string {
  const d = value.replace(/\D/g, '').slice(0, 13);
  return d.length > 12 ? `${d.slice(0, 5)}-${d.slice(5, 12)}-${d.slice(12)}` : d.length > 5 ? `${d.slice(0, 5)}-${d.slice(5)}` : d;
}
export const validPhone = (value: string) => /^\+92-[1-9]\d{9}$/.test(value);
export const validCnic = (value: string) => /^\d{5}-\d{7}-\d$/.test(value);
