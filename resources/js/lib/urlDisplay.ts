// Tiny helpers for showing URLs in the bookmark list.

/**
 * Hostname without a leading "www.". Graceful on malformed input:
 * returns the original string (trimmed) when it cannot be parsed.
 */
export function host(url: string): string {
    if (!url) {
        return '';
    }

    try {
        const h = new URL(url).hostname;

        return h.replace(/^www\./i, '');
    } catch {
        return String(url).trim();
    }
}

/**
 * A best-effort favicon URL for a bookmark that has none stored:
 * "/favicon.ico" at the origin. Returns null on bad input.
 */
export function favicon(url: string): string | null {
    try {
        return new URL('/favicon.ico', url).toString();
    } catch {
        return null;
    }
}

export default { host, favicon };
