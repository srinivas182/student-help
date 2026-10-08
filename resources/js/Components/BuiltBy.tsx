/**
 * Attribution, shown on every page for every role.
 *
 * rel carries noopener and noreferrer because it opens in a new tab, and the
 * link is deliberately quiet: it belongs in the footer, not competing with the
 * page.
 */
export default function BuiltBy({ className = '' }: { className?: string }) {
    return (
        <p className={`text-center text-xs text-slate-500 ${className}`}>
            Designed &amp; Developed by{' '}
            <a
                href="https://www.mayuraconsultancy.com"
                target="_blank"
                rel="noopener noreferrer"
                className="font-medium text-slate-600 underline-offset-2 transition hover:text-slate-900 hover:underline"
            >
                Mayura Consultancy Services
            </a>
        </p>
    );
}
