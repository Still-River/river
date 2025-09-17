import { NavLink } from 'react-router-dom';

export function Navigation() {
  const baseClasses = 'rounded-md px-3 py-2 text-sm font-medium transition-colors';
  const activeClasses = 'bg-slate-900 text-white';
  const inactiveClasses = 'text-slate-600 hover:bg-slate-200 hover:text-slate-900';

  return (
    <header className="border-b border-slate-200 bg-white/70 backdrop-blur">
      <nav className="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-4">
        <span className="text-lg font-semibold tracking-tight text-slate-900">River</span>
        <div className="flex gap-2">
          <NavLink
            to="/"
            end
            className={({ isActive }) => [
              baseClasses,
              isActive ? activeClasses : inactiveClasses,
            ].join(' ')}
          >
            Home
          </NavLink>
        </div>
      </nav>
    </header>
  );
}
