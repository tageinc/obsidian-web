const assert = require('assert');
const graph = require('../../public/js/solar-tracker-graph.js');

assert.equal(graph.celsiusToFahrenheit(0), 32);
assert.equal(graph.celsiusToFahrenheit(-40), -40);
assert.equal(graph.celsiusToFahrenheit('26.1135'), 79.0043);
[null, undefined, '', ' ', false, NaN, Infinity, 'unknown', '0x10', '0b11'].forEach(value => {
  assert.equal(graph.celsiusToFahrenheit(value), null, 'Missing and invalid temperatures stay missing');
});

const points = graph.normalizePoints([
  { id: 3, timestamp: '2026-03-08T10:30:00Z', temp: 40 },
  { id: 1, timestamp: '2026-03-08T09:30:00Z', temp: 10 }, // 1:30 AM PST
  { id: 2, timestamp: '2026-03-08T10:30:00Z', temp: 20 }, // 3:30 AM PDT
  { timestamp: 'not-a-date', temp: 99 }
]);
assert.equal(points.length, 3);
assert.deepEqual(points.map(point => point.id), [1, 2, 3]);
assert.deepEqual(points.map(point => point.temp), [10, 20, 40]);
assert.equal(points[1].epoch_ms, points[2].epoch_ms);
assert.ok(points[0].epoch_ms < points[1].epoch_ms);
assert.match(graph.formatTime(points[0]), /AM$/);
assert.match(graph.formatTime(points[1]), /AM$/);
assert.match(graph.rangeLabel(points), /Pacific Time/);
assert.deepEqual(graph.filteredPoints(points, 0), points);
assert.deepEqual(graph.filteredPoints(points, 0.5), [points[1], points[2]]);

const missingValues = graph.normalizePoints([
  { epoch_ms: 0, temp: null, ps1: undefined, ps2: '', motor_speed: ' ' },
  { epoch_ms: 1, temp: NaN, ps1: Infinity, ps2: 'not-a-number', motor_speed: false },
  { epoch_ms: 2, temp: 0, ps1: '0', ps2: 12.5, motor_speed: '6.25' }
]);
assert.equal(missingValues.length, 3);
assert.equal(missingValues[0].epoch_ms, 0);
['temp', 'ps1', 'ps2', 'motor_speed'].forEach(field => {
  assert.equal(missingValues[0][field], null);
  assert.equal(missingValues[1][field], null);
});
assert.deepEqual(['temp', 'ps1', 'ps2', 'motor_speed'].map(field => missingValues[2][field]), [0, 0, 12.5, 6.25]);
assert.deepEqual(graph.normalizePoints([
  { epoch_ms: 1, temp: 20 }, { epoch_ms: 1, temp: 40 }, { epoch_ms: 1, temp: 30 }
]).map(point => point.temp), [20, 40, 30]); // Legacy payloads keep source order for ties.

assert.match(graph.formatTime('2026-06-01T19:05:00Z'), /^12:05 PM$/); // noon in Pacific time
assert.match(graph.formatTime('2026-06-02T07:05:00Z'), /^12:05 AM$/); // midnight in Pacific time
assert.equal(graph.shouldIncludeDate(graph.normalizePoints([
  { timestamp: '2026-06-02T06:30:00Z' }, { timestamp: '2026-06-02T07:30:00Z' }
])), true); // 11:30 PM to 12:30 AM Pacific
assert.match(graph.formatTimestamp('2026-11-01T08:30:00Z', true), /1:30 AM PDT/);
assert.match(graph.formatTimestamp('2026-11-01T09:30:00Z', true), /1:30 AM PST/);

function closeTo(actual, expected, message) {
  assert.ok(Math.abs(actual - expected) < 1e-9, message || (actual + ' should equal ' + expected));
}

const regressionOrigin = Date.parse('2026-06-02T07:00:00Z');
function regressionData(samples) {
  return samples.map(([hours, y]) => ({ x: regressionOrigin + hours * 3600000, y }));
}

// Irregular spacing and noisy readings must use elapsed time, not point indexes or endpoint interpolation.
const noisy = regressionData([[3, 4], [0, 1], [1, 4]]);
const originalNoisy = JSON.parse(JSON.stringify(noisy));
const noisyFit = graph.linearRegression(noisy);
assert.deepEqual(noisyFit.map(point => point.x), [regressionOrigin, regressionOrigin + 3 * 3600000]);
closeTo(noisyFit[0].y, 13 / 7);
closeTo(noisyFit[1].y, 31 / 7);
assert.deepEqual(noisy, originalNoisy, 'Regression must not reorder or modify source readings');

// A repeated timestamp is another reading, not a group to average before fitting.
const duplicateFit = graph.linearRegression(regressionData([[0, 0], [0, 4], [1, 1], [2, 2]]));
closeTo(duplicateFit[0].y, 20 / 11);
closeTo(duplicateFit[1].y, 18 / 11);
assert.deepEqual(graph.linearRegression(regressionData([[0, 5], [2, 5], [7, 5]])), [
  { x: regressionOrigin, y: 5 }, { x: regressionOrigin + 7 * 3600000, y: 5 }
]);

const missingFit = graph.linearRegression([
  ...regressionData([[-1, null], [0, 2], [1, 4], [2, undefined], [3, Infinity], [4, NaN], [5, '12']]),
  { x: Infinity, y: 100 }, { x: null, y: 2 }, null
]);
assert.deepEqual(missingFit, [{ x: regressionOrigin, y: 2 }, { x: regressionOrigin + 3600000, y: 4 }]);
for (const insufficient of [[], [{ x: 0, y: 1 }], [{ x: 0, y: 1 }, { x: 0, y: 9 }], [{ x: 0, y: null }, { x: 1, y: 2 }]]) {
  assert.deepEqual(graph.linearRegression(insufficient), [], 'A fit requires valid readings at distinct timestamps');
}
const millisecondFit = graph.linearRegression([0, 1, 2].map(offset => ({ x: regressionOrigin + offset, y: offset })));
closeTo(millisecondFit[0].y, 0);
closeTo(millisecondFit[1].y, 2);

// Exercise the rendering entry point: formatting-only tests cannot catch a
// missing Chart reference or a broken range-control redraw.
const createdCharts = [];
const originalChart = globalThis.Chart;
globalThis.Chart = class {
  constructor(context, config) {
    this.context = context;
    this.config = config;
    this.destroyed = false;
    createdCharts.push(this);
  }
  destroy() { this.destroyed = true; }
};

function graphContainer() {
  const elements = {
    '[data-graph-range-label]': { textContent: '' },
    '[data-graph-empty]': { hidden: false }
  };
  ['temperature', 'panel', 'motor'].forEach(function (metric) {
    elements['[data-chart="' + metric + '"]'] = { getContext: () => metric };
  });
  const buttons = [1, 12, 24, 0].map(function (hours) {
    return {
      dataset: { graphHours: String(hours) },
      addEventListener(event, listener) { this[event] = listener; }
    };
  });
  return {
    elements,
    buttons,
    querySelector: selector => elements[selector],
    querySelectorAll: selector => selector === '[data-graph-hours]' ? buttons : []
  };
}

try {
  const container = graphContainer();
  graph.render(container, [
    { id: 4, timestamp: '2026-06-02T07:30:00Z', temp: 24, ps1: 45, ps2: 55, motor_speed: 12 },
    { id: 1, timestamp: '2026-06-02T05:30:00Z', temp: 20, ps1: 40, ps2: 50, motor_speed: 10 },
    { id: 3, timestamp: '2026-06-02T07:00:00Z', temp: 22, ps1: 42, ps2: 52, motor_speed: 11 },
    { id: 2, timestamp: '2026-06-02T07:00:00Z', temp: null, ps1: 0, ps2: null, motor_speed: 0 }
  ]);
  assert.equal(createdCharts.length, 3);
  assert.equal(container.elements['[data-graph-empty]'].hidden, true);
  assert.match(container.elements['[data-graph-range-label]'].textContent, /10:30 PM.*12:30 AM/);
  ['temperature', 'panel', 'motor'].forEach(function (metric, index) {
    const chart = createdCharts[index];
    assert.equal(chart.context, metric);
    assert.equal(chart.config.type, 'scatter');
    assert.deepEqual(chart.config.data.datasets[0].data.map(point => point.x), [
      Date.parse('2026-06-02T05:30:00Z'),
      Date.parse('2026-06-02T07:00:00Z'),
      Date.parse('2026-06-02T07:00:00Z'),
      Date.parse('2026-06-02T07:30:00Z')
    ]);
    const midnight = Date.parse('2026-06-02T07:00:00Z');
    assert.match(chart.config.options.scales.x.ticks.callback(midnight), /12:00 AM/);
    assert.match(chart.config.options.plugins.tooltip.callbacks.title([{ parsed: { x: midnight } }]), /12:00 AM PDT/);
    assert.equal(chart.config.options.plugins.tooltip.callbacks.afterLabel({ raw: { id: 2 } }), 'Reading #2');
    assert.equal(chart.config.options.plugins.tooltip.callbacks.afterLabel({ raw: {} }), '');
    chart.config.data.datasets.filter(dataset => !dataset.isTrend).forEach(dataset => {
      assert.equal(dataset.tension, 0);
      assert.equal(dataset.spanGaps, false);
      assert.equal(dataset.showLine, false);
      assert.equal(dataset.pointRadius, 2);
      assert.equal(dataset.pointHoverRadius, 4);
      assert.equal(dataset.pointStyle, 'circle');
      assert.equal(dataset.order, 0);
      assert.deepEqual(dataset.data.map(point => point.id), [1, 2, 3, 4]);
      assert.equal(chart.config.options.plugins.tooltip.filter({ dataset }), true);
    });
    chart.config.data.datasets.filter(dataset => dataset.isTrend).forEach(dataset => {
      assert.equal(dataset.type, 'line');
      assert.equal(dataset.showLine, true);
      assert.equal(dataset.pointRadius, 0);
      assert.equal(dataset.pointHoverRadius, 0);
      assert.equal(dataset.pointStyle, 'line');
      assert.equal(dataset.order, 1);
      assert.equal(dataset.borderWidth, 2);
      assert.deepEqual(dataset.borderDash, [6, 4]);
      assert.equal(dataset.tension, 0);
      assert.equal(dataset.fill, false);
      assert.equal(dataset.data.length, 2);
      assert.equal(chart.config.options.plugins.tooltip.filter({ dataset }), false);
    });
    assert.equal(chart.config.options.plugins.legend.display, true);
    assert.equal(chart.config.options.plugins.legend.labels.usePointStyle, true);
  });
  assert.equal(createdCharts[0].config.data.datasets[0].label, 'Temperature (°F)');
  assert.deepEqual(createdCharts[0].config.data.datasets[0].data.map(point => point.y), [68, null, 71.6, 75.2]);
  assert.deepEqual(createdCharts[1].config.data.datasets.map(dataset => dataset.label), ['PS1', 'PS2', 'PS1 — linear trend', 'PS2 — linear trend']);
  assert.deepEqual(createdCharts[1].config.data.datasets[0].data.map(point => point.y), [40, 0, 42, 45]);
  assert.deepEqual(createdCharts[1].config.data.datasets[1].data.map(point => point.y), [50, null, 52, 55]);
  assert.equal(createdCharts[1].config.options.plugins.legend.display, true);
  assert.equal(createdCharts[0].config.options.plugins.legend.display, true);
  assert.deepEqual(createdCharts[2].config.data.datasets[0].data.map(point => point.y), [10, 0, 11, 12]);
  assert.equal(createdCharts[0].config.options.scales.y.beginAtZero, false);
  assert.equal(createdCharts[1].config.options.scales.y.beginAtZero, false);
  const motorYAxis = createdCharts[2].config.options.scales.y;
  assert.equal(motorYAxis.beginAtZero, true, 'Motor speed includes zero even when readings stay on one side');
  assert.equal(motorYAxis.ticks.autoSkip, false, 'The zero label cannot be skipped');
  assert.equal(motorYAxis.ticks.maxTicksLimit, 7);
  const positiveTicks = { ticks: [{ value: 5 }, { value: 10 }] };
  motorYAxis.afterBuildTicks(positiveTicks);
  assert.deepEqual(positiveTicks.ticks.map(tick => tick.value), [0, 5, 10]);
  motorYAxis.afterBuildTicks(positiveTicks);
  assert.deepEqual(positiveTicks.ticks.map(tick => tick.value), [0, 5, 10], 'Zero is inserted only once');
  const negativeTicks = { ticks: [{ value: -10 }, { value: -5 }] };
  motorYAxis.afterBuildTicks(negativeTicks);
  assert.deepEqual(negativeTicks.ticks.map(tick => tick.value), [-10, -5, 0]);
  assert.equal(motorYAxis.grid, undefined, 'Motor speed uses the default grid styling');
  closeTo(createdCharts[0].config.data.datasets[1].data[0].y, (258 / 13) * 9 / 5 + 32);
  closeTo(createdCharts[0].config.data.datasets[1].data[1].y, (306 / 13) * 9 / 5 + 32);

  container.buttons[0].click();
  assert.equal(createdCharts.length, 6);
  assert.ok(createdCharts.slice(0, 3).every(chart => chart.destroyed));
  assert.deepEqual(createdCharts[3].config.data.datasets[0].data.map(point => point.y), [null, 71.6, 75.2]);
  assert.deepEqual(createdCharts[3].config.data.datasets[1].data.map(point => point.x), [
    Date.parse('2026-06-02T07:00:00Z'), Date.parse('2026-06-02T07:30:00Z')
  ], 'Changing the range must fit its valid readings again without extrapolation');
  closeTo(createdCharts[3].config.data.datasets[1].data[0].y, 71.6);
  closeTo(createdCharts[3].config.data.datasets[1].data[1].y, 75.2);
  container.buttons[3].click();
  assert.equal(createdCharts.length, 9);
  assert.equal(createdCharts[6].config.data.datasets[0].data.length, 4);
  closeTo(createdCharts[6].config.data.datasets[1].data[0].y, (258 / 13) * 9 / 5 + 32);

  const emptyContainer = graphContainer();
  graph.render(emptyContainer, []);
  assert.equal(createdCharts.length, 9);
  assert.equal(emptyContainer.elements['[data-graph-empty]'].hidden, false);
  assert.equal(emptyContainer.elements['[data-graph-range-label]'].textContent, 'No telemetry recorded');

  const rangedContainer = graphContainer();
  const latestEpoch = Date.parse('2026-06-02T19:00:00Z');
  graph.render(rangedContainer, [48, 24, 12, 1, 0].map((hours, index) => ({
    id: index + 1, epoch_ms: latestEpoch - hours * 3600000,
    temp: index, ps1: index, ps2: index * 2, motor_speed: index
  })));
  assert.match(rangedContainer.elements['[data-graph-range-label]'].textContent, /May 31.*Jun 2/);
  [[0, [3, 4]], [1, [2, 3, 4]], [2, [1, 2, 3, 4]], [3, [0, 1, 2, 3, 4]]].forEach(([buttonIndex, values]) => {
    rangedContainer.buttons[buttonIndex].click();
    const chart = createdCharts[createdCharts.length - 3];
    assert.deepEqual(chart.config.data.datasets[0].data.map(point => point.y), values.map(value => graph.celsiusToFahrenheit(value)));
    assert.equal(chart.config.data.datasets[1].data[0].x, chart.config.data.datasets[0].data[0].x);
    assert.equal(chart.config.data.datasets[1].data[1].x, latestEpoch);
    assert.match(chart.config.options.scales.x.ticks.callback(latestEpoch), /12:00 PM/);
  });

  const sparseContainer = graphContainer();
  graph.render(sparseContainer, [{ id: 1, epoch_ms: latestEpoch, temp: 0, ps1: null, ps2: 0, motor_speed: null }]);
  assert.equal(sparseContainer.elements['[data-graph-empty]'].hidden, true);
  assert.deepEqual(createdCharts[createdCharts.length - 3].config.data.datasets[0].data, [{ x: latestEpoch, y: 32, id: 1 }]);
  assert.ok(createdCharts.slice(-3).every(chart => chart.config.data.datasets.every(dataset => !dataset.isTrend)));

  const invalidTemperatureContainer = graphContainer();
  graph.render(invalidTemperatureContainer, [{ epoch_ms: latestEpoch, temp: 0, temp_f: null }]);
  assert.equal(createdCharts[createdCharts.length - 3].config.data.datasets[0].data[0].y, null, 'The validated Fahrenheit value preserves malformed source temperatures as missing');
  graph.render(invalidTemperatureContainer, [{ epoch_ms: latestEpoch, temp: '0x10' }]);
  assert.equal(createdCharts[createdCharts.length - 3].config.data.datasets[0].data[0].y, null, 'Older payloads must not turn hexadecimal strings into temperature readings');

  const duplicateContainer = graphContainer();
  graph.render(duplicateContainer, [10, 20].map((value, index) => ({
    id: index, epoch_ms: latestEpoch, temp: value, ps1: value, ps2: null, motor_speed: null
  })));
  assert.ok(createdCharts.slice(-3).every(chart => chart.config.data.datasets.every(dataset => !dataset.isTrend)), 'Duplicate times alone cannot define a trend');
} finally {
  if (originalChart === undefined) delete globalThis.Chart;
  else globalThis.Chart = originalChart;
}
