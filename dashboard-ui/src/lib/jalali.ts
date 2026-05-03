/**
 * Gregorian → Jalali (Shamsi), same algorithm as PHP SimpleVPBot_Jalali_Date::gregorian_to_jalali.
 */

export function gregorianToJalali(gy: number, gm: number, gd: number): [number, number, number] {
  const gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
  const jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29]

  let gY = gy - 1600
  let gM = gm - 1
  let gD = gd - 1

  let gDayNo =
    365 * gY +
    Math.floor((gY + 3) / 4) -
    Math.floor((gY + 99) / 100) +
    Math.floor((gY + 399) / 400)

  for (let i = 0; i < gM; i++) {
    gDayNo += gDaysInMonth[i]!
  }
  if (gM > 1 && ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0)) {
    gDayNo++
  }
  gDayNo += gD

  let jDayNo = gDayNo - 79
  const jNp = Math.floor(jDayNo / 12053)
  jDayNo %= 12053

  let jy = 979 + 33 * jNp + 4 * Math.floor(jDayNo / 1461)
  jDayNo %= 1461

  if (jDayNo >= 366) {
    jy += Math.floor((jDayNo - 1) / 365)
    jDayNo = (jDayNo - 1) % 365
  }

  let j = 0
  for (; j < 11 && jDayNo >= jDaysInMonth[j]!; j++) {
    jDayNo -= jDaysInMonth[j]!
  }
  const jm = j + 1
  const jd = jDayNo + 1
  return [jy, jm, jd]
}
