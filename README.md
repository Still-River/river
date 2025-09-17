# River - Evidence-Informed Productivity

River is a personal productivity app built on behavioral psychology and expert practice. Its purpose is simple but ambitious: help people clarify what matters most (values), turn that into meaningful goals, build identity-based habits, and actually execute with focus through daily planning and limited work-in-progress.

## Workflow

1. **Values** → Start by surfacing and clustering 5–7 core values, then refine them into a personal mission statement.
2. **Goals** → Create SMARTER goals linked to those values, with motivators, WOOP if-then plans, rewards, and tiny next actions.
3. **Habits** → Translate goals into daily behaviors using cues, habit stacking, and tiny versions for consistency.
4. **Execution** → Plan and track with a unified Today list, Big 3 priorities, Kanban boards with WIP limits, and end-of-day reviews.
5. **Reflection** → Journals and scheduled reviews keep the system adaptive and values-aligned over time.

## Features

- Guided values discovery and mission statement drafting
- SMARTER goal composer with intrinsic/extrinsic motivators
- Habit creation with cues, stacking, and streak tracking
- Unified action list with Big 3 priorities and focus mode
- Daily planner with timeboxing and end-of-day review prompts
- Kanban boards with WIP limits for project focus
- Journaling library with periodic reflection prompts
- Privacy by default, with export options (JSON, CSV, Markdown/PDF)

## Tech Stack

- Slim 4 with PHP 8.2 (API service)
- MySQL 8.3 (database)
- React 18 with React Router 6 and Tailwind CSS (frontend)
- Vite for dev tooling and bundling
- Docker Compose for local orchestration

## Directory Layout

- backend/: Slim application code, composer setup, API routes
- frontend/: React and Vite project with Tailwind styling

## Prerequisites

- Docker and Docker Compose v2

## Getting Started

1. Copy environment defaults:

   cp backend/.env.example backend/.env

2. Start the stack:

   docker compose up --build

3. Visit the services:
   - API: http://localhost:8080
   - Frontend: http://localhost:5173 (uses VITE_API_URL pointing at the API)

Docker volumes persist composer vendors, node modules, and database data across restarts.

## Environment Variables

- backend/.env now includes the existing application settings (APP_ENV, APP_DEBUG, APP_URL, DB_*) plus the Google OAuth and session keys: FRONTEND_APP_URL, GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI, GOOGLE_SCOPES, GOOGLE_PROMPT, GOOGLE_ACCESS_TYPE, CORS_ALLOWED_ORIGINS, SESSION_NAME, SESSION_LIFETIME, SESSION_DOMAIN, SESSION_SECURE, SESSION_SAME_SITE.
- Frontend relies on VITE_API_URL and VITE_GOOGLE_CLIENT_ID (defined in frontend/.env or docker-compose.yml).

If MySQL fails to start or the `db` container exits immediately, see `docs/troubleshooting-mysql.md` for reset steps.

## Google OAuth Setup

1. Create a Google Cloud project with an OAuth 2.0 Web application credential.
2. Set the authorized redirect URI to `http://localhost:8080/auth/google/callback` (update this for deployed environments).
3. Copy the client ID and secret into `backend/.env` (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`) and, if desired, `frontend/.env` (`VITE_GOOGLE_CLIENT_ID`). If you add multiple scopes, wrap them in quotes (for example `GOOGLE_SCOPES="openid email profile"`).
4. Restart the Docker stack so the containers pick up the new environment variables.
5. Visit the frontend, click **Sign in with Google**, and the API will persist the user record in the `users` table (created on first login).

## Backend Notes

- Entry point: backend/public/index.php
- Routes registered in backend/src/Routes/ApiRoutes.php
- Sample health endpoint: GET /health
- Journals API powers the values workflow:
  - GET /journals lists available journals
  - GET /journals/{slug} retrieves prompts plus the signed-in user's saved responses
  - PUT /journals/{slug}/responses saves responses, active step, and todo/skipped state for the authenticated session

## Frontend Notes

- Router defined in frontend/src/routes/index.tsx
- Tailwind configuration lives in frontend/tailwind.config.ts
- Primary layout component is frontend/src/components/AppLayout.tsx

## Development Tips

- PHP packages: edit backend/composer.json, then run inside the API container:

  docker compose exec api composer install

- JS packages: edit frontend/package.json, then run:

  docker compose exec frontend npm install package-name

- Database access:

  docker compose exec db mysql -u river -p river

## Deployment Considerations

- Build a production image from backend/Dockerfile and a separate Node build stage for the frontend.
- Configure persistent storage for MySQL when deploying (for example, Hostinger volumes).
- Set secrets and environment variables through your hosting provider rather than committing them.

## Contributing

1. Fork and clone the repository.
2. Create a feature branch.
3. Update or add tests where possible.
4. Submit a pull request describing the changes.
