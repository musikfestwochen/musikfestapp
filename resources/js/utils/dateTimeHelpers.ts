export const DATE_TIME_LOCALE = 'de-CH';
export const RELATIVE_TIME_LOCALE = 'en';

type DateTimeInput = Date | number | string;
type DurationStyle = 'long' | 'short';

const formatterCache = new Map<string, Intl.DateTimeFormat>();
const relativeFormatter = new Intl.RelativeTimeFormat(RELATIVE_TIME_LOCALE, { numeric: 'auto' });

function dateValue(value: DateTimeInput): Date | null {
    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

function formatter(options: Intl.DateTimeFormatOptions, timeZone?: string): Intl.DateTimeFormat {
    const resolvedOptions = { ...options, hourCycle: 'h23' as const, timeZone };
    const key = JSON.stringify(resolvedOptions);
    const cached = formatterCache.get(key);

    if (cached) {
        return cached;
    }

    const created = new Intl.DateTimeFormat(DATE_TIME_LOCALE, resolvedOptions);
    formatterCache.set(key, created);

    return created;
}

function format(value: DateTimeInput, options: Intl.DateTimeFormatOptions, timeZone?: string): string {
    const date = dateValue(value);

    return date ? formatter(options, timeZone).format(date) : 'N/A';
}

export function formatDate(value: DateTimeInput, timeZone?: string): string {
    return format(value, { day: '2-digit', month: '2-digit', year: 'numeric' }, timeZone);
}

export function formatDateTime(value: DateTimeInput, timeZone?: string): string {
    return format(value, { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }, timeZone);
}

export function formatDateTimeWithSeconds(value: DateTimeInput, timeZone?: string): string {
    return format(
        value,
        { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' },
        timeZone,
    ).replace(',', '');
}

export function formatTime(value: DateTimeInput, timeZone?: string): string {
    return format(value, { hour: '2-digit', minute: '2-digit' }, timeZone);
}

export function formatChartTick(value: DateTimeInput, showDate: boolean): string {
    return showDate ? format(value, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : formatTime(value);
}

export function formatChartTooltip(value: DateTimeInput): string {
    return format(value, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

export function formatRelativeTime(value: DateTimeInput, now = Date.now()): string {
    const date = dateValue(value);

    if (!date) {
        return 'N/A';
    }

    const diffSeconds = Math.round((date.getTime() - now) / 1000);
    if (Math.abs(diffSeconds) < 60) {
        return relativeFormatter.format(diffSeconds, 'second');
    }

    if (Math.abs(diffSeconds) < 3600) {
        return relativeFormatter.format(Math.round(diffSeconds / 60), 'minute');
    }

    if (Math.abs(diffSeconds) < 86400) {
        return relativeFormatter.format(Math.round(diffSeconds / 3600), 'hour');
    }

    return relativeFormatter.format(Math.round(diffSeconds / 86400), 'day');
}

export function formatDuration(milliseconds: number, options: { style?: DurationStyle } = {}): string {
    const style = options.style ?? 'long';
    let seconds = Math.max(0, Math.round(milliseconds / 1000));
    const units = [
        { seconds: 86400, short: 'd', long: 'day' },
        { seconds: 3600, short: 'h', long: 'hour' },
        { seconds: 60, short: 'm', long: 'minute' },
        { seconds: 1, short: 's', long: 'second' },
    ];
    const parts: string[] = [];

    for (const unit of units) {
        const value = Math.floor(seconds / unit.seconds);

        if (value === 0) {
            continue;
        }

        parts.push(style === 'short' ? `${value}${unit.short}` : `${value} ${unit.long}${value === 1 ? '' : 's'}`);
        seconds %= unit.seconds;

        if (parts.length === 2) {
            break;
        }
    }

    return parts.join(' ') || (style === 'short' ? '0s' : '0 seconds');
}

export function utcStringToDatetimeLocal(utcString?: string): string {
    if (!utcString) {
        return '';
    }

    const date = dateValue(utcString);

    return date ? new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16) : '';
}

export function datetimeLocalToUTCString(datetimeLocal: string): string {
    if (!datetimeLocal) {
        return '';
    }

    return dateValue(datetimeLocal)?.toISOString() ?? '';
}

export function getUserTimezone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}
