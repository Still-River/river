import { Link } from 'react-router-dom';

export function NotFoundPage() {
  return (
    <section className="flex flex-col items-start gap-4">
      <h1 className="text-2xl font-semibold text-slate-900">Page not found</h1>
      <p className="text-slate-600">The page you were looking for does not exist.</p>
      <Link
        to="/"
        className="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-700"
      >
        Back to home
      </Link>
    </section>
  );
}
