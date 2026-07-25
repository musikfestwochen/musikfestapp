export function metersPerSecondToKilometersPerHour(value: number): number {
    return value * 3.6;
}

export function formatWindSpeed(value: number): string {
    return Number(metersPerSecondToKilometersPerHour(value).toFixed(1)).toString();
}

export function stageSafetySensorName(sensor: { location: string | null; name: string | null; identifier: string }): string {
    return sensor.location || sensor.name || sensor.identifier;
}
