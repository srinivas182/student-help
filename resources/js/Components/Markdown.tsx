import { Fragment, ReactNode } from 'react';

/**
 * Renders the lesson notes properly instead of dumping raw markdown into a
 * <pre>, which is what made finished lessons look like plain text.
 *
 * Written rather than pulled in: the content is a known, reviewed subset
 * (headings, emphasis, lists, tables, code, rules), and nothing here ever
 * interprets HTML, so a lesson cannot inject markup into the page.
 */

function inline(text: string, keyPrefix: string): ReactNode[] {
    const nodes: ReactNode[] = [];
    // Bold, italic and inline code, in one pass so they cannot nest wrongly
    const pattern = /(\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`)/g;
    const parts = text.split(pattern);

    parts.forEach((part, index) => {
        const key = `${keyPrefix}-${index}`;

        if (part.startsWith('**') && part.endsWith('**') && part.length > 4) {
            nodes.push(
                <strong key={key} className="font-semibold text-slate-900">
                    {part.slice(2, -2)}
                </strong>,
            );
        } else if (part.startsWith('`') && part.endsWith('`') && part.length > 2) {
            nodes.push(
                <code
                    key={key}
                    className="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[0.9em] text-slate-800"
                >
                    {part.slice(1, -1)}
                </code>,
            );
        } else if (part.startsWith('*') && part.endsWith('*') && part.length > 2) {
            nodes.push(
                <em key={key} className="italic">
                    {part.slice(1, -1)}
                </em>,
            );
        } else if (part) {
            nodes.push(<Fragment key={key}>{part}</Fragment>);
        }
    });

    return nodes;
}

export default function Markdown({ source, className = '' }: { source: string; className?: string }) {
    const lines = source.replace(/\r\n/g, '\n').split('\n');
    const blocks: ReactNode[] = [];

    let index = 0;
    let key = 0;

    while (index < lines.length) {
        const line = lines[index];
        const trimmed = line.trim();

        if (trimmed === '') {
            index++;
            continue;
        }

        // Headings
        const heading = /^(#{1,4})\s+(.*)$/.exec(trimmed);
        if (heading) {
            const level = heading[1].length;
            const text = inline(heading[2], `h${key}`);
            const styles: Record<number, string> = {
                1: 'mt-6 first:mt-0 text-xl font-semibold text-slate-900',
                2: 'mt-6 first:mt-0 text-lg font-semibold text-slate-900',
                3: 'mt-5 first:mt-0 text-base font-semibold text-slate-900',
                4: 'mt-4 first:mt-0 text-sm font-semibold text-slate-800',
            };

            blocks.push(
                <p key={key++} className={styles[level]}>
                    {text}
                </p>,
            );
            index++;
            continue;
        }

        // Horizontal rule
        if (/^(-{3,}|\*{3,}|_{3,})$/.test(trimmed)) {
            blocks.push(<hr key={key++} className="my-5 border-slate-200" />);
            index++;
            continue;
        }

        // Fenced code
        if (trimmed.startsWith('```')) {
            const code: string[] = [];
            index++;

            while (index < lines.length && !lines[index].trim().startsWith('```')) {
                code.push(lines[index]);
                index++;
            }

            index++;
            blocks.push(
                <pre
                    key={key++}
                    className="my-4 overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs leading-relaxed text-slate-100"
                >
                    <code>{code.join('\n')}</code>
                </pre>,
            );
            continue;
        }

        // Tables
        if (trimmed.startsWith('|') && index + 1 < lines.length && /^\|[\s|:-]+\|$/.test(lines[index + 1].trim())) {
            const cells = (row: string) =>
                row.trim().replace(/^\||\|$/g, '').split('|').map((cell) => cell.trim());

            const header = cells(trimmed);
            index += 2;
            const rows: string[][] = [];

            while (index < lines.length && lines[index].trim().startsWith('|')) {
                rows.push(cells(lines[index]));
                index++;
            }

            blocks.push(
                <div key={key++} className="my-4 overflow-x-auto">
                    <table className="w-full border-collapse text-sm">
                        <thead>
                            <tr className="border-b border-slate-300">
                                {header.map((cell, i) => (
                                    <th key={i} className="px-3 py-2 text-left font-semibold text-slate-900">
                                        {inline(cell, `th${i}`)}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, rowIndex) => (
                                <tr key={rowIndex} className="border-b border-slate-100">
                                    {row.map((cell, cellIndex) => (
                                        <td key={cellIndex} className="px-3 py-2 align-top text-slate-700">
                                            {inline(cell, `td${rowIndex}-${cellIndex}`)}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>,
            );
            continue;
        }

        // Blockquote
        if (trimmed.startsWith('> ')) {
            const quote: string[] = [];

            while (index < lines.length && lines[index].trim().startsWith('> ')) {
                quote.push(lines[index].trim().slice(2));
                index++;
            }

            blocks.push(
                <blockquote
                    key={key++}
                    className="my-4 border-l-4 border-indigo-200 bg-indigo-50/50 py-2 pl-4 text-sm text-slate-700"
                >
                    {inline(quote.join(' '), `bq${key}`)}
                </blockquote>,
            );
            continue;
        }

        // Lists, ordered and unordered
        const bullet = /^[-*+]\s+(.*)$/.exec(trimmed);
        const numbered = /^\d+[.)]\s+(.*)$/.exec(trimmed);

        if (bullet || numbered) {
            const ordered = Boolean(numbered);
            const items: string[] = [];

            while (index < lines.length) {
                const current = lines[index].trim();
                const match = ordered ? /^\d+[.)]\s+(.*)$/.exec(current) : /^[-*+]\s+(.*)$/.exec(current);

                if (!match) {
                    break;
                }

                items.push(match[1]);
                index++;
            }

            const ListTag = ordered ? 'ol' : 'ul';

            blocks.push(
                <ListTag
                    key={key++}
                    className={`my-3 space-y-1.5 pl-5 text-sm leading-relaxed text-slate-700 ${
                        ordered ? 'list-decimal' : 'list-disc'
                    }`}
                >
                    {items.map((item, i) => (
                        <li key={i} className="pl-1">
                            {inline(item, `li${key}-${i}`)}
                        </li>
                    ))}
                </ListTag>,
            );
            continue;
        }

        // Paragraph: gather until a blank line
        const paragraph: string[] = [];

        while (index < lines.length && lines[index].trim() !== '' && !/^(#{1,4}\s|[-*+]\s|\d+[.)]\s|>\s|\||```)/.test(lines[index].trim())) {
            paragraph.push(lines[index].trim());
            index++;
        }

        if (paragraph.length > 0) {
            blocks.push(
                <p key={key++} className="my-3 text-sm leading-relaxed text-slate-700">
                    {inline(paragraph.join(' '), `p${key}`)}
                </p>,
            );
        } else {
            index++;
        }
    }

    return <div className={className}>{blocks}</div>;
}
