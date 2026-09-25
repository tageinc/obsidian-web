const stateColors = {
    online: '#00FF00',
    'online-unregistered': '#0000FF',
    offline: '#808080',
    'low-voltage': '#FFFF00',
    'extreme-weather': '#FFA500',
    'theft-vandalism': '#800080',
    'battery-dead': '#FF0000',
    idle: '#000000',
    'set-up': '#FA8072',
    calibration: '#A52A2A',
    'solar-track': '#008000',
    sleep: '#ADD8E6',
    safe: '#800080',
    'remote-control': '#FFA500',
};

export function statusColor(state) {
    return (
        stateColors[
            String(state ?? '')
                .toLowerCase()
                .replaceAll(' ', '-')
        ] ?? '#808080'
    );
}

export function coordinates(device) {
    const values = [device.latitude, device.longitude];
    if (
        values.some(
            (value) => value == null || String(value).trim() === '' || typeof value === 'boolean',
        )
    )
        return null;
    const [latitude, longitude] = values.map(Number);
    return Number.isFinite(latitude) &&
        Number.isFinite(longitude) &&
        Math.abs(latitude) <= 90 &&
        Math.abs(longitude) <= 180
        ? [latitude, longitude]
        : null;
}

export function mapDevices(payload, showAll) {
    const devices = showAll ? payload : payload?.data;
    if (
        !Array.isArray(devices) ||
        devices.some((device) => !device || typeof device !== 'object' || Array.isArray(device))
    ) {
        throw new Error('Could not load device locations. Try again.');
    }
    return devices;
}

export function devicePopup(device) {
    const popup = document.createElement('div');
    const rows = [
        ['Name', device.name ?? 'Unnamed device'],
        [
            'Address',
            [
                device.address_1,
                device.address_2,
                device.city,
                device.address_state,
                device.zip_code,
                device.country,
            ]
                .filter(Boolean)
                .join(', ') || 'No Address',
        ],
        ['Last Updated', device.last_updated || 'No data'],
    ];
    for (const [label, value] of rows) {
        const row = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = `${label}: `;
        row.append(title, document.createTextNode(String(value)));
        popup.append(row);
    }
    return popup;
}

export function markerIcon(state) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('width', '30');
    svg.setAttribute('height', '30');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('fill', statusColor(state));
    path.setAttribute(
        'd',
        'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z',
    );
    svg.append(path);
    return svg;
}
