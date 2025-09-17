export function HomePage() {
  return (
    <section className="space-y-6">
      <h1 className="text-3xl font-bold tracking-tight text-slate-900">Welcome to River</h1>
      <p className="max-w-2xl text-lg text-slate-600">
        This is the starting point for the River project. The frontend is powered by React Router and Tailwind CSS,
        while the backend runs on Slim PHP. Use this boilerplate to build your application features quickly.
      </p>
      <div className="rounded-lg border border-dashed border-slate-300 bg-white p-6">
        <p className="text-sm text-slate-500">
          Update this page with project-specific messaging, quick links, or onboarding details for your teammates.
        </p>
      </div>
    </section>
  );
}
