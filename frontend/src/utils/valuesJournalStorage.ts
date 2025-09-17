type PromptKey = 'strongMemories' | 'admiredPerson' | 'recurringSituations' | 'legacyVision';

export type ValuesJournalResponses = Record<PromptKey, string>;

export interface ValuesJournalState {
  responses: ValuesJournalResponses;
  activeStep: number;
  todo: boolean;
  lastUpdated?: string;
  skippedAt?: string;
}

export const VALUES_JOURNAL_STORAGE_KEY = 'river.valuesJournal.v1';

export const defaultValuesJournalState: ValuesJournalState = {
  responses: {
    strongMemories: '',
    admiredPerson: '',
    recurringSituations: '',
    legacyVision: '',
  },
  activeStep: 0,
  todo: false,
};

export function loadValuesJournalState(): ValuesJournalState {
  if (typeof window === 'undefined') {
    return defaultValuesJournalState;
  }

  try {
    const raw = window.localStorage.getItem(VALUES_JOURNAL_STORAGE_KEY);
    if (!raw) {
      return defaultValuesJournalState;
    }

    const parsed = JSON.parse(raw) as Partial<ValuesJournalState>;
    return {
      ...defaultValuesJournalState,
      ...parsed,
      responses: {
        ...defaultValuesJournalState.responses,
        ...(parsed.responses ?? {}),
      },
      activeStep:
        typeof parsed.activeStep === 'number'
          ? parsed.activeStep
          : defaultValuesJournalState.activeStep,
      todo: Boolean(parsed.todo),
    };
  } catch (error) {
    console.error('Failed to parse values journal state from storage', error);
    return defaultValuesJournalState;
  }
}

export function saveValuesJournalState(state: ValuesJournalState) {
  if (typeof window === 'undefined') {
    return;
  }

  window.localStorage.setItem(
    VALUES_JOURNAL_STORAGE_KEY,
    JSON.stringify(state),
  );
}

export function clearValuesJournalTodo() {
  if (typeof window === 'undefined') {
    return;
  }

  const current = loadValuesJournalState();
  if (!current.todo) {
    return;
  }

  const next: ValuesJournalState = {
    ...current,
    todo: false,
  };
  saveValuesJournalState(next);
}
