import { NavLink } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export function Navigation() {
  const { user, loading, loginWithGoogle, logout, error } = useAuth();
  const baseClasses = 'rounded-md px-3 py-2 text-sm font-medium transition-colors';
  const activeClasses = 'bg-slate-900 text-white';
  const inactiveClasses = 'text-slate-600 hover:bg-slate-200 hover:text-slate-900';

  return (
    <header className="border-b border-slate-200 bg-white/70 backdrop-blur">
      <nav className="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-4">
        <span className="text-lg font-semibold tracking-tight text-slate-900">River</span>
        <div className="flex items-center gap-4">
          <div className="flex gap-2">
            <NavLink
              to="/"
              end
              className={({ isActive }) => [baseClasses, isActive ? activeClasses : inactiveClasses].join(' ')}
            >
              Home
            </NavLink>
            <NavLink
              to="/values/journal"
              className={({ isActive }) => [baseClasses, isActive ? activeClasses : inactiveClasses].join(' ')}
            >
              Values Journal
            </NavLink>
          </div>
          <div className="flex items-center gap-3">
            {error ? (
              <span className="text-xs text-red-600">{error}</span>
            ) : null}
            {user ? (
              <div className="flex items-center gap-2">
                {user.avatarUrl ? (
                  <img
                    src={user.avatarUrl}
                    alt={user.name ?? user.email}
                    className="h-8 w-8 rounded-full border border-slate-200 object-cover"
                  />
                ) : (
                  <span className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold uppercase text-white">
                    {(user.name ?? user.email).slice(0, 2)}
                  </span>
                )}
                <button
                  type="button"
                  onClick={() => {
                    void logout();
                  }}
                  className="rounded-full border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                >
                  Log out
                </button>
              </div>
            ) : (
              <button
                type="button"
                onClick={() => {
                  void loginWithGoogle();
                }}
                disabled={loading}
                className="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
              >
                {loading ? 'Loading...' : 'Sign in with Google'}
              </button>
            )}
          </div>
        </div>
      </nav>
    </header>
  );
}
