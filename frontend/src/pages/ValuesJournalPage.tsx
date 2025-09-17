import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { MarkdownEditor } from '../components/MarkdownEditor';
import { useAuth } from '../contexts/AuthContext';
import { api } from '../utils/api';
import type { JournalDetailResponse, SaveJournalResponse } from '../utils/api';

const VALUES_JOURNAL_SLUG = 'values-journal';

type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

type JournalState = {
  responses: Record<string, string>;
  activeStep: number;
  todo: boolean;
  skippedAt: string | null;
  lastUpdated: string | null;
};

const INITIAL_STATE: JournalState = {
  responses: {},
  activeStep: 0,
  todo: false,
  skippedAt: null,
  lastUpdated: null,
};

export function ValuesJournalPage() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const [journal, setJournal] = useState<JournalDetailResponse['journal'] | null>(null);
  const [state, setState] = useState<JournalState>(INITIAL_STATE);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
  const saveTimerRef = useRef<number | null>(null);
  const skipNextSaveRef = useRef(true);

  const sendSaveRequest = useCallback(
    async (snapshot: JournalState) => {
      if (!user || !journal) {
        return;
      }

      try {
        const payload = {
          responses: snapshot.responses,
          activeStep: snapshot.activeStep,
          todo: snapshot.todo,
          skippedAt: snapshot.skippedAt,
        };

        const result = await api.put<SaveJournalResponse>(
          `/journals/${journal.slug}/responses`,
          payload,
        );

        skipNextSaveRef.current = true;
        saveTimerRef.current = null;
        setError(null);
        setState((previous) => ({
          ...previous,
          activeStep: result.state.activeStep,
          todo: result.state.todo,
          skippedAt: result.state.skippedAt,
          lastUpdated: result.state.lastUpdated,
        }));
        setSaveStatus('saved');
      } catch (err) {
        saveTimerRef.current = null;
        setSaveStatus('error');
        setError(err instanceof Error ? err.message : 'Failed to save journal');
      }
    },
    [journal, user],
  );

  useEffect(() => {
    let cancelled = false;

    const loadJournal = async () => {
      setLoading(true);
      try {
        const data = await api.get<JournalDetailResponse>(`/journals/${VALUES_JOURNAL_SLUG}`);
        if (cancelled) {
          return;
        }

        setJournal(data.journal);

        const responses: Record<string, string> = {};
        data.journal.prompts.forEach((prompt) => {
          responses[prompt.key] = data.responses[prompt.key] ?? '';
        });

        setState({
          responses,
          activeStep: data.userState?.activeStep ?? 0,
          todo: data.userState?.todo ?? false,
          skippedAt: data.userState?.skippedAt ?? null,
          lastUpdated: data.userState?.lastUpdated ?? null,
        });
        skipNextSaveRef.current = true;
        setError(null);
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : 'Failed to load journal');
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    };

    void loadJournal();

    return () => {
      cancelled = true;
      if (saveTimerRef.current !== null) {
        window.clearTimeout(saveTimerRef.current);
      }
    };
  }, [user]);

  useEffect(() => {
    if (!user || !journal) {
      return;
    }

    if (skipNextSaveRef.current) {
      skipNextSaveRef.current = false;
      return;
    }

    if (saveTimerRef.current !== null) {
      window.clearTimeout(saveTimerRef.current);
    }

    setSaveStatus('saving');

    saveTimerRef.current = window.setTimeout(() => {
      const snapshot: JournalState = {
        responses: { ...state.responses },
        activeStep: state.activeStep,
        todo: state.todo,
        skippedAt: state.skippedAt,
        lastUpdated: state.lastUpdated,
      };
      void sendSaveRequest(snapshot);
    }, 500);

    return () => {
      if (saveTimerRef.current !== null) {
        window.clearTimeout(saveTimerRef.current);
      }
    };
  }, [journal, state.activeStep, state.responses, state.skippedAt, state.todo, user]);

  const prompts = journal?.prompts ?? [];
  const canEdit = Boolean(user);

  const requiredPromptKeys = useMemo(
    () => prompts.filter((prompt) => !prompt.optional).map((prompt) => prompt.key),
    [prompts],
  );

  const currentPrompt = prompts[state.activeStep] ?? prompts[0] ?? null;

  const completedRequiredPrompts = useMemo(() => {
    if (requiredPromptKeys.length === 0) {
      return true;
    }
    return requiredPromptKeys.every((key) => (state.responses[key] ?? '').trim().length > 0);
  }, [requiredPromptKeys, state.responses]);

  const updateResponse = useCallback(
    (key: string, value: string) => {
      if (!canEdit) {
        return;
      }

      setState((previous) => {
        const responses = {
          ...previous.responses,
          [key]: value,
        };

        const shouldClearTodo = requiredPromptKeys.every(
          (requiredKey) => (responses[requiredKey] ?? '').trim().length > 0,
        );

        return {
          ...previous,
          responses,
          lastUpdated: new Date().toISOString(),
          todo: shouldClearTodo ? false : previous.todo,
        };
      });
    },
    [canEdit, requiredPromptKeys],
  );

  const goToStep = useCallback(
    (stepIndex: number) => {
      if (prompts.length === 0) {
        return;
      }

      setState((previous) => {
        const boundedIndex = Math.min(prompts.length - 1, Math.max(0, stepIndex));
        if (boundedIndex === previous.activeStep) {
          return previous;
        }

        return {
          ...previous,
          activeStep: boundedIndex,
        };
      });
    },
    [prompts.length],
  );

  const handleNext = () => {
    goToStep(state.activeStep + 1);
  };

  const handlePrevious = () => {
    goToStep(state.activeStep - 1);
  };

  const handleSkip = () => {
    if (!canEdit || !journal) {
      navigate('/');
      return;
    }

    const snapshot: JournalState = {
      responses: { ...state.responses },
      activeStep: state.activeStep,
      todo: true,
      skippedAt: new Date().toISOString(),
      lastUpdated: new Date().toISOString(),
    };

    if (saveTimerRef.current !== null) {
      window.clearTimeout(saveTimerRef.current);
      saveTimerRef.current = null;
    }

    skipNextSaveRef.current = true;
    setSaveStatus('saving');
    setState(snapshot);
    void sendSaveRequest(snapshot);
    navigate('/');
  };

  const handleComplete = () => {
    if (!completedRequiredPrompts) {
      return;
    }

    if (!canEdit || !journal) {
      return;
    }

    const snapshot: JournalState = {
      responses: { ...state.responses },
      activeStep: state.activeStep,
      todo: false,
      skippedAt: null,
      lastUpdated: new Date().toISOString(),
    };

    if (saveTimerRef.current !== null) {
      window.clearTimeout(saveTimerRef.current);
      saveTimerRef.current = null;
    }

    skipNextSaveRef.current = true;
    setSaveStatus('saving');
    setState(snapshot);
    void sendSaveRequest(snapshot);

    navigate('/');
  };

  const saveIndicator = useMemo(() => {
    if (saveStatus === 'saving') {
      return 'Saving...';
    }

    if (saveStatus === 'error') {
      return 'Error saving';
    }

    if (saveStatus === 'saved') {
      return 'Saved';
    }

    if (state.lastUpdated) {
      return `Last updated ${new Date(state.lastUpdated).toLocaleString()}`;
    }

    return 'Autosave ready';
  }, [saveStatus, state.lastUpdated]);

  if (loading) {
    return (
      <section className="space-y-6">
        <h1 className="text-3xl font-bold tracking-tight text-slate-900">Values Journal</h1>
        <p className="text-sm text-slate-600">Loading journal...</p>
      </section>
    );
  }

  if (error && !journal) {
    return (
      <section className="space-y-6">
        <h1 className="text-3xl font-bold tracking-tight text-slate-900">Values Journal</h1>
        <p className="text-sm text-red-600">{error}</p>
      </section>
    );
  }

  if (!journal || !currentPrompt) {
    return null;
  }

  return (
    <section className="space-y-8">
      <header className="space-y-3">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight text-slate-900">
              {journal.title}
            </h1>
            <p className="text-sm text-slate-600">{journal.description}</p>
          </div>
          <div className="text-sm font-medium text-slate-500">{saveIndicator}</div>
        </div>
        {!canEdit ? (
          <p className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Sign in to save your journal responses.
          </p>
        ) : null}
        {error && canEdit ? (
          <p className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {error}
          </p>
        ) : null}
      </header>

      <nav className="flex flex-wrap gap-2">
        {prompts.map((prompt, index) => {
          const isActive = index === state.activeStep;
          const isComplete = (state.responses[prompt.key] ?? '').trim().length > 0;
          return (
            <button
              type="button"
              key={prompt.key}
              onClick={() => goToStep(index)}
              className={`rounded-full px-4 py-2 text-sm font-medium transition ${
                isActive
                  ? 'bg-slate-900 text-white shadow'
                  : isComplete
                  ? 'border border-slate-300 bg-white text-slate-700 hover:border-slate-400'
                  : 'border border-slate-200 bg-white text-slate-500 hover:border-slate-300'
              }`}
            >
              {prompt.title}
            </button>
          );
        })}
      </nav>

      <article className="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <header className="space-y-2">
          <p className="text-sm font-semibold uppercase tracking-wide text-slate-500">
            Prompt {state.activeStep + 1} of {prompts.length}
          </p>
          <h2 className="text-2xl font-semibold text-slate-900">{currentPrompt.title}</h2>
          <p className="text-sm text-slate-600">{currentPrompt.question}</p>
        </header>

        <section className="space-y-4 rounded-lg bg-slate-50 p-4">
          <h3 className="text-sm font-semibold text-slate-700">Examples</h3>
          <ul className="space-y-2 text-sm text-slate-600">
            {currentPrompt.examples.map((example) => (
              <li key={example} className="rounded-md bg-white/70 p-3 shadow-sm">
                {example}
              </li>
            ))}
          </ul>
        </section>

        <section className="space-y-3">
          <h3 className="text-sm font-semibold text-slate-700">Guidance</h3>
          <p className="text-sm text-slate-600">{currentPrompt.guidance}</p>
        </section>

        <MarkdownEditor
          id={currentPrompt.key}
          value={state.responses[currentPrompt.key] ?? ''}
          onChange={(value) => updateResponse(currentPrompt.key, value)}
          placeholder={currentPrompt.placeholder}
          readOnly={!canEdit}
        />
      </article>

      <footer className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex gap-2">
          <button
            type="button"
            onClick={handlePrevious}
            disabled={state.activeStep === 0}
            className="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-400 disabled:cursor-not-allowed disabled:opacity-60"
          >
            Previous
          </button>
          <button
            type="button"
            onClick={handleNext}
            disabled={state.activeStep >= prompts.length - 1}
            className="rounded-full border border-slate-900 px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-slate-900 hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
          >
            Next
          </button>
        </div>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={handleSkip}
            disabled={!canEdit}
            className="rounded-full border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 transition hover:bg-amber-100"
          >
            Skip for now
          </button>
          <button
            type="button"
            onClick={handleComplete}
            disabled={!completedRequiredPrompts || !canEdit}
            className="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
          >
            Mark journal complete
          </button>
        </div>
      </footer>
    </section>
  );
}
