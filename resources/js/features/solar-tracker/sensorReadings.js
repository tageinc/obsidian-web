export function celsiusToFahrenheit(value) {
    if (typeof value !== 'number' && typeof value !== 'string') return null;
    if (
        typeof value === 'string' &&
        !/^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/.test(value.trim())
    ) {
        return null;
    }
    const celsius = Number(value);
    if (!Number.isFinite(celsius)) return null;
    const fahrenheit = (celsius * 9) / 5 + 32;
    if (!Number.isFinite(fahrenheit)) return null;
    const scaled = Math.abs(fahrenheit) * 10000;
    return Number.isFinite(scaled)
        ? (Math.sign(fahrenheit) * Math.round(scaled)) / 10000
        : fahrenheit;
}

export function ctsState(value) {
    if (value === 0 || value === '0') return 'Open';
    if (value === 1 || value === '1') return 'Closed';
    return null;
}
