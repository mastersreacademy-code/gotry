export const steps = ['Business', 'Activity', 'Trial Lesson', 'Regular Lessons', 'Review'];
export const draftKey = 'gotry-provider-draft-v1';
export function validateStep(step, data) {
  const errors = {};
  const required = (key, label) => { if (!String(data[key] ?? '').trim()) errors[key] = label + ' is required'; };
  if (step === 0) { required('businessName','Business name'); required('contactName','Contact name'); required('email','Email'); if (data.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) errors.email='Enter a valid email'; }
  if (step === 1) { required('activityName','Activity name'); required('category','Category'); required('description','Description'); required('location','Location'); if (!(Number(data.minAge)>=0 && Number(data.maxAge)>=Number(data.minAge))) errors.maxAge='Enter a valid age range'; }
  if (step === 2) { required('trialType','Trial type'); required('trialDate','Trial date'); if (data.trialType === 'group' && !(Number(data.minParticipants)>0 && Number(data.maxParticipants)>=Number(data.minParticipants))) errors.maxParticipants='Maximum must be at least the minimum'; }
  if (step === 3) { required('regularDescription','Regular lesson details'); if (!(Number(data.regularPrice)>=0)) errors.regularPrice='Enter a valid price'; }
  return errors;
}
export function safeDraft(input) {
 const allowed=['businessName','contactName','email','phone','website','activityName','category','description','location','minAge','maxAge','trialType','trialDate','trialTime','minParticipants','maxParticipants','deadline','regularDescription','regularPrice','discountType','discountValue'];
 return Object.fromEntries(allowed.map(k=>[k,typeof input?.[k]==='string'?input[k].slice(0,3000):'']));
}
