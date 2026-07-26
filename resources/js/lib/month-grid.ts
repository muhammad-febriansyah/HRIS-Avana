/**
 * Date maths shared by the calendar screens: turning a `YYYY-MM` string into
 * the cells of a month grid, and stepping between months.
 */

/** One cell of a month grid; `inMonth` is false for the padding days. */
export interface DayCell {
    date: string;
    day: number;
    inMonth: boolean;
}

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/** Build a `YYYY-MM-DD` string from numeric parts (1-based month). */
export function ymd(year: number, month: number, day: number): string {
    return `${year}-${pad(month)}-${pad(day)}`;
}

/**
 * The leading, in-month, and trailing day cells for a month, always a whole
 * number of weeks so the grid never ends ragged. Weeks start on Sunday.
 */
export function buildMonthCells(month: string): DayCell[] {
    const [year, monthNo] = month.split('-').map(Number);
    const firstWeekday = new Date(year, monthNo - 1, 1).getDay(); // 0 = Sunday
    const daysInMonth = new Date(year, monthNo, 0).getDate();
    const daysInPrev = new Date(year, monthNo - 1, 0).getDate();

    const cells: DayCell[] = [];

    // Leading days from the previous month.
    for (let i = firstWeekday - 1; i >= 0; i--) {
        const day = daysInPrev - i;
        const prevMonth = monthNo === 1 ? 12 : monthNo - 1;
        const prevYear = monthNo === 1 ? year - 1 : year;

        cells.push({
            date: ymd(prevYear, prevMonth, day),
            day,
            inMonth: false,
        });
    }

    for (let day = 1; day <= daysInMonth; day++) {
        cells.push({ date: ymd(year, monthNo, day), day, inMonth: true });
    }

    // Trailing days to complete the final week.
    let nextDay = 1;

    while (cells.length % 7 !== 0) {
        const nextMonth = monthNo === 12 ? 1 : monthNo + 1;
        const nextYear = monthNo === 12 ? year + 1 : year;

        cells.push({
            date: ymd(nextYear, nextMonth, nextDay),
            day: nextDay,
            inMonth: false,
        });
        nextDay++;
    }

    return cells;
}

/** Shift a `YYYY-MM` string by a number of months, in either direction. */
export function shiftMonth(month: string, delta: number): string {
    const [year, monthNo] = month.split('-').map(Number);
    const total = year * 12 + (monthNo - 1) + delta;

    return `${Math.floor(total / 12)}-${pad((total % 12) + 1)}`;
}

/** Today as `YYYY-MM-DD`, in the viewer's own timezone. */
export function todayYmd(): string {
    const now = new Date();

    return ymd(now.getFullYear(), now.getMonth() + 1, now.getDate());
}
