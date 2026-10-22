export const MAX_MESSAGES = 12;
export const MAX_LENGTH = 2000;

export function trimHistory(messages, limit = MAX_MESSAGES) {
    return (Array.isArray(messages) ? messages : [])
        .filter(m =>
            (m?.role === 'user' || m?.role === 'assistant')
            && typeof m.content === 'string'
            && m.content.trim().length > 0
        )
        .map(m => ({role: m.role, content: m.content.trim().slice(0, MAX_LENGTH)}))
        .slice(-limit);
}

export function isValidMessage(m) {
    return Boolean(m)
        && (m.role === 'user' || m.role === 'assistant')
        && typeof m.content === 'string'
        && m.content.trim().length > 0
        && m.content.length <= MAX_LENGTH;
}
