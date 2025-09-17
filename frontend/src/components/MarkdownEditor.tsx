import { useMemo, useRef, useState } from 'react';

type FormattingToken = {
  label: string;
  prefix: string;
  suffix?: string;
  description: string;
};

const FORMATTING_TOKENS: FormattingToken[] = [
  { label: 'Bold', prefix: '**', suffix: '**', description: 'Wrap selection with **' },
  { label: 'Italic', prefix: '_', suffix: '_', description: 'Wrap selection with _' },
  { label: 'List', prefix: '- ', description: 'Start a bullet list item' },
  { label: 'Quote', prefix: '> ', description: 'Insert a block quote' },
  { label: 'Link', prefix: '[', suffix: '](url)', description: 'Convert selection into a link' },
];

const escapeHtml = (input: string) =>
  input
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

const applyInlineFormatting = (input: string) =>
  input
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/_(.+?)_/g, '<em>$1</em>')
    .replace(
      /\[([^\]]+)\]\(([^)]+)\)/g,
      (_match, label, url) =>
        `<a href="${url}" target="_blank" rel="noopener noreferrer">${label}</a>`,
    );

function convertMarkdownToHtml(markdown: string): string {
  const sanitized = escapeHtml(markdown);
  const lines = sanitized.split(/\n/);
  const htmlParts: string[] = [];
  let listBuffer: string[] = [];

  const flushList = () => {
    if (listBuffer.length > 0) {
      htmlParts.push(`<ul>${listBuffer.join('')}</ul>`);
      listBuffer = [];
    }
  };

  lines.forEach((line) => {
    const trimmed = line.trim();

    if (trimmed.startsWith('- ')) {
      const content = applyInlineFormatting(trimmed.slice(2).trim());
      listBuffer.push(`<li>${content}</li>`);
      return;
    }

    flushList();

    if (trimmed.startsWith('> ')) {
      htmlParts.push(
        `<blockquote>${applyInlineFormatting(trimmed.slice(2).trim())}</blockquote>`,
      );
      return;
    }

    if (trimmed.length === 0) {
      htmlParts.push('<br />');
      return;
    }

    htmlParts.push(`<p>${applyInlineFormatting(trimmed)}</p>`);
  });

  flushList();

  return htmlParts.join('');
}

export interface MarkdownEditorProps {
  id: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  label?: string;
  readOnly?: boolean;
}

export function MarkdownEditor({
  id,
  value,
  onChange,
  placeholder,
  label,
  readOnly = false,
}: MarkdownEditorProps) {
  const [showPreview, setShowPreview] = useState(false);
  const textareaRef = useRef<HTMLTextAreaElement | null>(null);
  const previewHtml = useMemo(() => convertMarkdownToHtml(value), [value]);

  const handleFormatting = (token: FormattingToken) => {
    const textarea = textareaRef.current;
    if (!textarea) {
      return;
    }

    const { selectionStart, selectionEnd, value: currentValue } = textarea;
    const selection = currentValue.slice(selectionStart, selectionEnd);
    const suffix = token.suffix ?? '';
    const inserted = `${token.prefix}${selection || ''}${suffix}`;
    const updated =
      currentValue.slice(0, selectionStart) +
      inserted +
      currentValue.slice(selectionEnd);

    onChange(updated);

    // Restore focus and caret placement after formatting helper runs
    requestAnimationFrame(() => {
      textarea.focus();
      const caretPosition = selection
        ? selectionStart + inserted.length
        : selectionStart + token.prefix.length;
      textarea.setSelectionRange(caretPosition, caretPosition);
    });
  };

  return (
    <div className="space-y-3">
      {label ? (
        <label className="block text-sm font-medium text-slate-700" htmlFor={id}>
          {label}
        </label>
      ) : null}
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap gap-2">
          {FORMATTING_TOKENS.map((token) => (
            <button
              key={token.label}
              type="button"
              className="rounded-md border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600 shadow-sm hover:bg-slate-100"
              onClick={() => handleFormatting(token)}
              title={token.description}
              disabled={readOnly}
            >
              {token.label}
            </button>
          ))}
        </div>
        <button
          type="button"
          className="text-xs font-medium text-slate-500 hover:text-slate-700"
          onClick={() => setShowPreview((prev) => !prev)}
        >
          {showPreview ? 'Hide preview' : 'Show preview'}
        </button>
      </div>
      <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <textarea
          id={id}
          ref={textareaRef}
          value={value}
          placeholder={placeholder}
          onChange={(event) => onChange(event.target.value)}
          className="h-48 w-full resize-y border-0 bg-transparent px-4 py-3 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-300"
          readOnly={readOnly}
        />
        {showPreview ? (
          <div className="border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-relaxed text-slate-700">
            {value.trim().length === 0 ? (
              <p className="italic text-slate-400">Nothing to preview yet. Start typing above.</p>
            ) : (
              <div
                className="markdown-preview space-y-3"
                dangerouslySetInnerHTML={{ __html: previewHtml }}
              />
            )}
          </div>
        ) : null}
      </div>
      <p className="text-xs text-slate-500">
        {readOnly
          ? 'Sign in to edit and save your reflections.'
          : 'Autosaves as you type. Use Markdown for quick formatting.'}
      </p>
    </div>
  );
}
