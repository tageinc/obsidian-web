import { expect, it } from 'vitest';
import { celsiusToFahrenheit, ctsState } from './sensorReadings';

it.each([
    [0, 32],
    [100, 212],
    [-40, -40],
    [26.1135, 79.0043],
    ['24', 75.2],
    [' 0 ', 32],
])('converts %s Celsius to %s Fahrenheit', (celsius, fahrenheit) => {
    expect(celsiusToFahrenheit(celsius)).toBe(fahrenheit);
});

it.each([null, undefined, '', ' ', 'bad', '0x10', false, true, [], {}, NaN, Infinity])(
    'does not turn invalid or missing temperature %s into a reading',
    (value) => expect(celsiusToFahrenheit(value)).toBeNull(),
);

it.each([
    [0, 'Open'],
    ['0', 'Open'],
    [1, 'Closed'],
    ['1', 'Closed'],
    [null, null],
    [undefined, null],
    ['', null],
    [false, null],
    [2, null],
])('interprets CTS %s as %s', (value, state) => {
    expect(ctsState(value)).toBe(state);
});
