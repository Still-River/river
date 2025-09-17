import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { loadValuesJournalState, VALUES_JOURNAL_STORAGE_KEY } from '../utils/valuesJournalStorage';

export function HomePage() {
  const { user } = useAuth();
  const [hasValuesJournalTodo, setHasValuesJournalTodo] = useState(false);

  useEffect(() => {
    const updateFromStorage = () => {
      const state = loadValuesJournalState();
      setHasValuesJournalTodo(state.todo);
    };

    updateFromStorage();

    if (typeof window === 'undefined') {
      return;
    }

    const handleStorage = (event: StorageEvent) => {
      if (event.key === VALUES_JOURNAL_STORAGE_KEY) {
        updateFromStorage();
      }
    };

    window.addEventListener('storage', handleStorage);
    return () => window.removeEventListener('storage', handleStorage);
  }, []);

  return (
    <section className="space-y-6">
      <div className="flex flex-col gap-2">
        <h1 className="text-3xl font-bold tracking-tight text-slate-900">Welcome to River</h1>
        {user ? (
          <p className="text-sm text-slate-600">
            Signed in as <span className="font-semibold text-slate-900">{user.name ?? user.email}</span>
          </p>
        ) : (
          <p className="text-sm text-slate-600">Sign in with Google to sync your progress across devices.</p>
        )}
      </div>
      <p className="max-w-2xl text-lg text-slate-600">
        This is the starting point for the River project. The frontend is powered by React Router and Tailwind CSS,
        while the backend runs on Slim PHP. Use this boilerplate to build your application features quickly.
      </p>
      <div className="rounded-lg border border-dashed border-slate-300 bg-white p-6">
        <p className="text-sm text-slate-500">
          Update this page with project-specific messaging, quick links, or onboarding details for your teammates.
        </p>
      </div>
      {hasValuesJournalTodo ? (
        <div className="rounded-lg border border-amber-200 bg-amber-50 p-6 shadow-sm">
          <h2 className="text-lg font-semibold text-amber-900">Finish your Values Priming Journal</h2>
          <p className="mt-2 text-sm text-amber-800">
            You skipped the priming journal earlier. Capture your reflections so River can tailor guidance to what matters most to you.
          </p>
          <div className="mt-4">
            <Link
              to="/values/journal"
              className="inline-flex items-center justify-center rounded-full bg-amber-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-800"
            >
              Resume journal
            </Link>
          </div>
        </div>
      ) : null}
    </section>
  );
}
