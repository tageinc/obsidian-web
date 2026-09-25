import { mkdtempSync, mkdirSync, writeFileSync, realpathSync, rmSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const repo = fileURLToPath(new URL('../..', import.meta.url));
if (!existsSync(path.join(repo, 'public/build/manifest.json'))) {
    throw new Error('Build the production frontend before browser tests: npm run build');
}
const root = mkdtempSync(path.join(tmpdir(), 'obsidian-browser-'));
const database = path.join(root, 'database.sqlite');
writeFileSync(database, '');
for (const directory of [
    'app/public',
    'framework/cache/data',
    'framework/sessions',
    'framework/views',
    'logs',
    'bootstrap',
]) {
    mkdirSync(path.join(root, 'storage', directory), { recursive: true });
}
const environment = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'false',
    APP_URL: 'http://127.0.0.1:8127',
    APP_KEY: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    APP_CONFIG_CACHE: path.join(root, 'config.php'),
    APP_ROUTES_CACHE: path.join(root, 'routes.php'),
    APP_SERVICES_CACHE: path.join(root, 'services.php'),
    APP_PACKAGES_CACHE: path.join(root, 'packages.php'),
    DATABASE_URL: '',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    DB_FOREIGN_KEYS: 'true',
    CACHE_DRIVER: 'array',
    SESSION_DRIVER: 'file',
    SESSION_COOKIE: 'obsidian_browser_test_session',
    SESSION_SECURE_COOKIE: 'false',
    MAIL_MAILER: 'array',
    LOG_CHANNEL: 'null',
    QUEUE_CONNECTION: 'sync',
    BCRYPT_ROUNDS: '4',
    REDIS_WORKLOADS_ENABLED: 'false',
    FRONTEND_VUE3_ENABLED: 'true',
    FRONTEND_DEV_SERVER: '',
    OBSIDIAN_BROWSER_TEST: '1',
    OBSIDIAN_BROWSER_TEST_ROOT: root,
};
// A inherited rollout exception must not silently reduce browser coverage.
for (const flag of [
    'PROFILE',
    'DEVICE_REGISTER',
    'DEVICE_EDIT',
    'DASHBOARD',
    'DEVICE_INFO',
    'AUTH',
    'DEVELOPER',
    'PUBLIC_PAGES',
    'WORKSPACE',
]) {
    environment[`FRONTEND_VUE3_${flag}`] = 'true';
}
function cleanup() {
    if (!existsSync(root)) return;
    const resolved = realpathSync(root);
    if (
        path.dirname(resolved) !== realpathSync(tmpdir()) ||
        !/^obsidian-browser-[\w-]+$/.test(path.basename(resolved))
    ) {
        throw new Error('Refusing cleanup outside the isolated browser-test directory.');
    }
    rmSync(resolved, { recursive: true, force: true });
}
const php = process.env.PHP_BINARY || 'php';
const seed = spawnSync(php, ['tests/browser/seed.php'], {
    cwd: repo,
    env: environment,
    stdio: 'inherit',
    windowsHide: true,
});
if (seed.status !== 0) {
    cleanup();
    process.exit(seed.status || 1);
}
const server = spawn(php, ['-S', '127.0.0.1:8127', '-t', 'public', 'tests/browser/router.php'], {
    cwd: repo,
    env: environment,
    stdio: ['ignore', 'inherit', 'pipe'],
    windowsHide: true,
});
let serverOutput = '';
server.stderr.on('data', (chunk) => {
    serverOutput += chunk.toString();
    const lines = serverOutput.split(/\r?\n/);
    serverOutput = lines.pop();
    for (const line of lines) {
        if (/^\[[^\]]+\] 127\.0\.0\.1:\d+ (?:Accepted|Closing|\[\d+\]:)/.test(line)) continue;
        process.stderr.write(`${line}\n`);
    }
});
for (const signal of ['SIGTERM', 'SIGINT']) process.on(signal, () => server.kill('SIGTERM'));
server.on('error', (error) => {
    cleanup();
    throw error;
});
server.on('exit', (code) => {
    cleanup();
    process.exit(code || 0);
});
