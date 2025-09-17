import type { ReactNode } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { MarkdownEditor } from '../components/MarkdownEditor';
import {
  loadValuesJournalState,
  saveValuesJournalState,
  ValuesJournalResponses,
  ValuesJournalState,
} from '../utils/valuesJournalStorage';

type PromptKey = keyof ValuesJournalResponses;

interface PromptConfig {
  key: PromptKey;
  title: string;
  question: string;
  examples: string[];
  guidance: string;
  placeholder: string;
  optional?: boolean;
}

const PROMPTS: PromptConfig[] = [
  {
    key: 'strongMemories',
    title: 'Strong Memories',
    question:
      'Bring to mind a vivid, meaningful moment. What happened, who was involved, and which personal values were present or tested?',
    examples: [
      'Helping my sister move apartments reminded me how much I prioritise generosity, reliability, and family support.',
      'When I turned down a promotion that conflicted with travel plans I had promised friends, I realised how strongly I value loyalty and shared experiences.',
      'Finishing my first marathon rekindled my commitment to resilience, disciplined practice, and self-respect.',
    ],
    guidance:
      `When a memory feels powerful, it usually carries an emotional charge that points to what matters most. Describe the scene with sensory detail—the sounds, expressions, and small decisions. Then ask why the moment still stands out: Did you feel proud, frustrated, or affirmed? Those feelings are clues about the values in motion. If the memory was uplifting, name the values you were actively living (perhaps courage, creativity, or belonging). If the memory was difficult, explore which values were squeezed or ignored and how you wished you could have responded. Give yourself permission to write freely without judging whether a recollection is “important enough.” You can always refine later. The aim here is discovering patterns: Do you keep honouring connection? Do you fight for fairness? Do quiet acts of care matter more than headline achievements? As you tag each value, note what it practically looks like for you—what actions, language, and boundaries demonstrate it in everyday life.`,
    placeholder:
      'Capture the scene, who was there, and list the values that were alive for you…',
  },
  {
    key: 'admiredPerson',
    title: 'Person You Admire',
    question:
      'Think of someone you genuinely admire. How do they show up in the world, and which values do they embody that you want to cultivate?',
    examples: [
      'My grandmother writes welcome notes to every new neighbour. She models hospitality, generosity, and steady presence.',
      'I admire my colleague Amina because she invites quiet voices into meetings. She lives inclusion, curiosity, and accountability.',
      'The community organiser I follow admits mistakes publicly, demonstrating humility, justice, and courage.',
    ],
    guidance:
      `Admiration is often projection: the traits you celebrate in someone else are the ones you are ready to practise. Describe specific behaviours rather than vague praise. How does this person treat people when no one is watching? What choices do they make when rushed or under pressure? Detailing concrete scenes keeps your reflection grounded. Next, translate each admired behaviour into a value word and define it in your own language. “Integrity” might mean telling the truth faster, “generosity” could mean unblocking teammates, and “creativity” might be giving yourself permission to iterate out loud. Finally, connect their example to your next decision. Where in your calendar or routines could you mirror one of these values this week? Be honest about any resistance and name the trade-offs you would need to accept. The more tangible you make these observations, the easier it becomes to practise them instead of only admiring them from afar.`,
    placeholder:
      'Describe the person, the behaviours you admire, and the values you want to practise…',
  },
  {
    key: 'recurringSituations',
    title: 'Recurring Situations',
    question:
      'Notice a situation that keeps showing up in your life. When you react, which values are driving you, and which do you want to bring forward next time?',
    examples: [
      'During weekly product reviews I get defensive. I care about craftsmanship, but I want to foreground learning and partnership instead.',
      'Family dinners often veer into politics, and I withdraw. I value harmony, yet I also want to practise courage and respectful dialogue.',
      'When deadlines compress, I skip breaks. It comes from ambition and responsibility, but I would rather lead with sustainability and trust.',
    ],
    guidance:
      `Patterns reveal value conflicts. Start by describing the recurring scene in detail: Who is present, what typically triggers you, and how does your body respond? Then list the values that fuel your default reaction. Perhaps efficiency makes you steamroll teammates, or loyalty makes you stay silent. None of these values are wrong; they simply might be overextended. Next, articulate the values you want to feature when the situation arises again. What would leading with patience, candour, or playfulness look like in concrete behaviour? Draft a short script or micro-habit that honours those values—maybe asking one curious question before offering advice, or scheduling a five-minute pause before replying. Finally, anticipate what might get in the way. Naming the friction (time pressure, fear of judgement, cultural norms) allows you to plan supportive cues or boundaries. By rehearsing the values you intend to practise, you create muscle memory that nudges you toward the future self you are building.`,
    placeholder:
      'Map the situation, your default reaction, and the values you will lead with next time…',
  },
  {
    key: 'legacyVision',
    title: 'Legacy Vision (Optional)',
    question:
      'Imagine your 80th-birthday celebration or a future moment where life feels deeply fulfilling. What do people thank you for, and which values shaped that legacy?',
    examples: [
      'At my 80th, friends describe how I built spaces where people felt seen—valuing belonging, hospitality, and steady mentorship.',
      'My future grandkids share stories about impromptu adventures, pointing to spontaneity, curiosity, and courage.',
      'Colleagues toast to a culture I protected that paired excellence with compassion and honest feedback.',
    ],
    guidance:
      `Legacy work is about orientation, not perfection. Picture the setting with colour: the food, music, faces, and small gestures. Let the people around you speak—what do they say you made possible? Translate every compliment into a value you practised consistently. If someone thanks you for “always showing up,” maybe consistency and devotion were your compass. If they celebrate how you challenged them, perhaps growth and truth-telling guided you. Once you surface the values, connect them to actionable rhythms: What would you need to stop, start, or continue this month to make that future story plausible? Embrace the emotional tone as well—are people laughing, feeling safe, or energised? Those cues help you prioritise values that shape atmosphere, not just achievement. Treat this exercise as a living invitation. You can revise it as seasons change, but writing it now gives you a north star that keeps everyday decisions aligned with the legacy you want to leave.`,
    placeholder:
      'Let the future scene unfold and highlight the values people celebrate in you…',
    optional: true,
  },
];

const REQUIRED_PROMPT_KEYS = PROMPTS.filter((prompt) => !prompt.optional).map(
  (prompt) => prompt.key,
);

type SaveStatus = 'idle' | 'saving' | 'saved';

export function ValuesJournalPage() {
  const navigate = useNavigate();
  const [state, setState] = useState<ValuesJournalState>(() => loadValuesJournalState());
  const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
  const initialisedRef = useRef(false);

  useEffect(() => {
    if (!initialisedRef.current) {
      initialisedRef.current = true;
    } else {
      setSaveStatus('saving');
    }

    saveValuesJournalState(state);

    if (typeof window === 'undefined') {
      return;
    }

    const timer = window.setTimeout(() => {
      setSaveStatus('saved');
    }, 350);

    return () => {
      if (typeof window !== 'undefined') {
        window.clearTimeout(timer);
      }
    };
  }, [state]);

  const currentPrompt = PROMPTS[state.activeStep];

  const completedRequiredPrompts = useMemo(
    () =>
      REQUIRED_PROMPT_KEYS.every(
        (key) => state.responses[key].trim().length > 0,
      ),
    [state.responses],
  );

  const updateResponse = (key: PromptKey, value: string) => {
    setState((previous) => {
      const responses: ValuesJournalResponses = {
        ...previous.responses,
        [key]: value,
      };

      const shouldClearTodo = REQUIRED_PROMPT_KEYS.every(
        (requiredKey) => responses[requiredKey].trim().length > 0,
      );

      return {
        ...previous,
        responses,
        lastUpdated: new Date().toISOString(),
        todo: shouldClearTodo ? false : previous.todo,
      };
    });
  };

  const goToStep = (stepIndex: number) => {
    setState((previous) => {
      const boundedIndex = Math.min(
        PROMPTS.length - 1,
        Math.max(0, stepIndex),
      );
      if (boundedIndex === previous.activeStep) {
        return previous;
      }
      return {
        ...previous,
        activeStep: boundedIndex,
      };
    });
  };

  const handleNext = () => {
    goToStep(state.activeStep + 1);
  };

  const handlePrevious = () => {
    goToStep(state.activeStep - 1);
  };

  const handleSkip = () => {
    setState((previous) => ({
      ...previous,
      todo: true,
      skippedAt: new Date().toISOString(),
    }));
    navigate('/');
  };

  const handleComplete = () => {
    if (!completedRequiredPrompts) {
      return;
    }

    setState((previous) => ({
      ...previous,
      todo: false,
      lastUpdated: new Date().toISOString(),
    }));

    navigate('/');
  };

  const saveIndicator = useMemo(() => {
    if (saveStatus === 'saving') {
      return 'Saving…';
    }

    if (saveStatus === 'saved') {
      return 'Saved';
    }

    if (state.lastUpdated) {
      return `Last updated ${new Date(state.lastUpdated).toLocaleString()}`;
    }

    return 'Autosave ready';
  }, [saveStatus, state.lastUpdated]);

  return (
    <section className="space-y-8">
      <header className="space-y-3">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm font-medium uppercase tracking-wide text-slate-500">
              Values Priming Journal
            </p>
            <h1 className="text-3xl font-bold tracking-tight text-slate-900">
              Orient before you dive into River
            </h1>
          </div>
          <div className="flex flex-col items-start gap-1 text-sm text-slate-500 sm:items-end">
            <span>{saveIndicator}</span>
            {state.todo ? (
              <span className="flex items-center gap-2 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                Resume later added to your to-dos
              </span>
            ) : null}
          </div>
        </div>
        <p className="max-w-3xl text-base text-slate-600">
          Spend a few minutes naming the values that already guide you. Your answers stay private to you. Hop back in anytime—everything saves automatically.
        </p>
      </header>

      <nav aria-label="Values journal progress" className="space-y-4">
        <div className="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-slate-500">
          <span>
            Step {state.activeStep + 1} of {PROMPTS.length}
          </span>
          <button
            type="button"
            onClick={handleSkip}
            className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-200"
          >
            Skip for now
          </button>
        </div>
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
          {PROMPTS.map((prompt, index) => {
            const isActive = index === state.activeStep;
            const isComplete = state.responses[prompt.key].trim().length > 0;
            return (
              <button
                key={prompt.key}
                type="button"
                onClick={() => goToStep(index)}
                className={[
                  'flex h-12 flex-col items-start justify-center rounded-lg border px-3 text-left transition',
                  isActive
                    ? 'border-slate-900 bg-slate-900 text-white shadow'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50',
                ].join(' ')}
              >
                <span className="text-[10px] font-semibold uppercase tracking-wider">
                  {prompt.optional ? 'Optional' : 'Prompt'} {index + 1}
                </span>
                <span className="text-xs font-medium">
                  {prompt.title}
                  {isComplete ? ' ✓' : ''}
                </span>
              </button>
            );
          })}
        </div>
      </nav>

      <article className="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div className="space-y-2">
          <span className="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
            {currentPrompt.optional ? 'Optional prompt' : 'Reflection prompt'}
          </span>
          <h2 className="text-2xl font-semibold text-slate-900">
            {currentPrompt.title}
          </h2>
          <p className="text-base text-slate-600">{currentPrompt.question}</p>
        </div>

        <div className="flex flex-col gap-4 sm:flex-row">
          <TogglePanel label="Examples">
            <ul className="space-y-3 text-sm text-slate-600">
              {currentPrompt.examples.map((example, index) => (
                <li key={index} className="rounded-lg bg-slate-50 p-3">
                  {example}
                </li>
              ))}
            </ul>
          </TogglePanel>
          <TogglePanel label="Guidance" defaultOpen>
            <p className="text-sm leading-relaxed text-slate-600">
              {currentPrompt.guidance}
            </p>
          </TogglePanel>
        </div>

        <MarkdownEditor
          id={`values-journal-${currentPrompt.key}`}
          value={state.responses[currentPrompt.key]}
          onChange={(value) => updateResponse(currentPrompt.key, value)}
          placeholder={currentPrompt.placeholder}
        />

        <div className="flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="text-xs text-slate-500">
            {completedRequiredPrompts
              ? 'All core prompts captured. Feel free to keep refining or finish up.'
              : 'Complete the core prompts to finish your priming journal.'}
          </div>
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              onClick={handlePrevious}
              disabled={state.activeStep === 0}
              className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
            >
              Previous
            </button>
            <button
              type="button"
              onClick={handleNext}
              disabled={state.activeStep === PROMPTS.length - 1}
              className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
            >
              Next
            </button>
            <button
              type="button"
              onClick={handleComplete}
              disabled={!completedRequiredPrompts}
              className="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40"
            >
              Save & Continue to River
            </button>
          </div>
        </div>
      </article>
    </section>
  );
}

interface TogglePanelProps {
  label: string;
  children: ReactNode;
  defaultOpen?: boolean;
}

function TogglePanel({ label, children, defaultOpen = false }: TogglePanelProps) {
  const [isOpen, setIsOpen] = useState(defaultOpen);

  return (
    <div className="flex-1 rounded-xl border border-slate-200 bg-slate-50 p-4">
      <button
        type="button"
        onClick={() => setIsOpen((previous) => !previous)}
        className="mb-2 flex w-full items-center justify-between text-left text-sm font-semibold text-slate-700"
        aria-expanded={isOpen}
      >
        {label}
        <span className="text-xs font-medium text-slate-500">
          {isOpen ? 'Hide' : 'Show'}
        </span>
      </button>
      {isOpen ? <div className="space-y-2 text-sm text-slate-600">{children}</div> : null}
    </div>
  );
}
