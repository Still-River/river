# AGENTS

This document helps automated agents and new contributors explore the River repository efficiently.

## Repository Map
- backend/: Slim PHP API (entry point in public/index.php, routes in src/Routes)
- frontend/: React application bootstrapped with Vite (routes in src/routes, layout in src/components)
- docker-compose.yml: orchestrates api, frontend, and db services
- README.md: high level project orientation and setup steps

## Development Containers
- api service: PHP 8.2 FPM container from backend/Dockerfile
- frontend service: Node 20 Alpine container running Vite dev server
- db service: MySQL 8.3 with persisted data in volume db_data

## Common Commands
- Start stack: docker compose up --build
- Stop stack: docker compose down
- Run PHP composer inside container: docker compose exec api composer <args>
- Run npm inside container: docker compose exec frontend npm <args>
- Access MySQL shell: docker compose exec db mysql -u river -p river

## Environment
- Copy backend/.env.example to backend/.env before running services
- Frontend expects VITE_API_URL (configured in docker-compose.yml)
- Database credentials must stay in sync between backend/.env and docker-compose.yml

## Coding Notes
- PHP code uses PSR-4 autoloading with namespace App\
- API routes grouped in App\Routes\ApiRoutes
- React app uses React Router data APIs (createBrowserRouter) and Tailwind utility classes
- Favor TypeScript strictness; adjust tsconfig if library types require

## Testing Guidance
- Add PHP unit tests with PHPUnit or Pest (not yet configured)
- Add frontend tests with Vitest or React Testing Library (not yet configured)
- Prefer containerized execution for tests to keep parity with development environment

## Task Workflow
1. Sync dependencies using container commands above.
2. Make minimal, well-scoped changes.
3. Run linting or tests if configured in future updates.
4. Document behavior updates in README.md or other relevant docs.

## Support
- If commands fail because services are not running, ensure docker compose up has been executed.
- For persistent issues, review container logs with docker compose logs <service>.
