// Store UTC instants while accepting local wall-clock times in Australia/Sydney.
// Reject ambiguous DST fall-back times and nonexistent spring-forward times.
const zone='Australia/Sydney';
const format=new Intl.DateTimeFormat('en-CA',{timeZone:zone,year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'});
function wall(instant){const p=Object.fromEntries(format.formatToParts(new Date(instant)).filter(x=>x.type!=='literal').map(x=>[x.type,x.value]));return p.year+'-'+p.month+'-'+p.day+'T'+p.hour+':'+p.minute;}
export function sydneyToUtc(local){
 if(typeof local!=='string'||!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(local))throw Error('Use YYYY-MM-DDTHH:mm');
 const base=Date.parse(local+'Z');if(!Number.isFinite(base))throw Error('Invalid date');
 const matches=[10,11].map(offset=>new Date(base-offset*3600000)).filter(d=>wall(d)===local);
 if(matches.length!==1)throw Error(matches.length?'Ambiguous Sydney daylight-saving time':'Nonexistent Sydney local time');
 return matches[0].toISOString();
}
export function utcToSydney(iso){const ms=Date.parse(iso);if(!Number.isFinite(ms))throw Error('Invalid UTC instant');return wall(ms);}
export const SYDNEY_TIMEZONE=zone;
