import { Outlet } from 'react-router-dom';
import { Navigation } from './Navigation';

export function AppLayout() {
  return (
    <div className="min-h-screen bg-slate-50 text-slate-900">
      <Navigation />
      <main className="mx-auto w-full max-w-5xl px-6 py-10">
        <Outlet />
      </main>
    </div>
  );
}
