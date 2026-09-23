# Docker and deployment rules

- Treat Docker, Compose, workflows, environment settings, and scripts as production-impacting code. Keep changes within the requested scope and inspect the actual deployment path.
- This repository contains production Docker configuration. Consult `Dockerfile`, `compose.yaml`, `docker/deploy.sh`, and [deployment documentation](../docs/github-actions-deployment.md); do not import TAGCSOFT's local-only nginx/node/queue topology.
- The separate Node build stage runs `npm ci` and `npm run build`. The PHP image receives `public/build`; Node need not be installed in the serving container.
- Preserve asset verification during image construction and before app replacement. Missing manifests, chunks, or CSS must fail deployment.
- Preserve the pinned toolchain and lockfile workflow. Docker may reuse unchanged successful build layers; do not require cache deletion for every deploy.
- Keep secrets in their configured stores. Never overwrite the shared production environment file with an example or print its full contents.
- Vue flags and assets are independent. Check effective Laravel flags as well as compiled files. Per-page flags override the master switch.
- Recreate the app container to apply changed environment values, and clear stale configuration/view caches through the deployment workflow. A restart alone does not reload container environment settings.
- Protect database and upload volumes. Do not run `docker compose down -v`, remove persistent data, or alter storage topology without explicit authorization for that operation.
- Preserve scheduler locking, migration ordering, smoke checks, and deployment failure behavior. Review their consequences when editing startup/deployment logic.
- Respect optional Redis profiles and configured database, filesystem, session, mail, and queue settings; do not assume services or workers exist.
- Report what was verified locally versus in Docker or production. Do not claim a deployment occurred based on a successful local build.
