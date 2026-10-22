import test from 'node:test';
import assert from 'node:assert/strict';
import {MAX_LENGTH, MAX_MESSAGES, isValidMessage, trimHistory} from '../../resources/js/platform/chat.js';

test('trimHistory keeps only the last messages', () => {
    const many = Array.from({length: 15}, (_, i) => ({role: i % 2 ? 'assistant' : 'user', content: String(i)}));
    const trimmed = trimHistory(many);
    assert.equal(trimmed.length, MAX_MESSAGES);
    assert.equal(trimmed[0].content, '3');
    assert.equal(trimmed.at(-1).content, '14');
    assert.equal(trimHistory(many, 3).length, 3);
    assert.equal(trimHistory(many, 3)[0].content, '12');
});

test('trimHistory drops system, null, empty and non-string messages', () => {
    const cleaned = trimHistory([
        {role: 'system', content: 'Секретная инструкция'},
        null,
        {role: 'user', content: '   '},
        {role: 'assistant', content: 42},
        {role: 'user', content: '  Привет  '},
    ]);
    assert.deepEqual(cleaned, [{role: 'user', content: 'Привет'}]);
});

test('trimHistory truncates a message to 2000 characters', () => {
    const [message] = trimHistory([{role: 'user', content: 'я'.repeat(MAX_LENGTH + 5)}]);
    assert.equal(message.content.length, MAX_LENGTH);
});

test('trimHistory tolerates non-array input', () => {
    assert.deepEqual(trimHistory(null), []);
    assert.deepEqual(trimHistory(undefined), []);
    assert.deepEqual(trimHistory('не массив'), []);
});

test('isValidMessage accepts only well-formed user and assistant messages', () => {
    assert.ok(isValidMessage({role: 'user', content: 'Куда сходить с ребёнком?'}));
    assert.ok(isValidMessage({role: 'assistant', content: 'В музей.'}));
    assert.ok(!isValidMessage({role: 'system', content: 'Инструкция'}));
    assert.ok(!isValidMessage({role: 'user', content: '   '}));
    assert.ok(!isValidMessage({role: 'user', content: 'x'.repeat(MAX_LENGTH + 1)}));
    assert.ok(!isValidMessage(null));
    assert.ok(!isValidMessage({role: 'user'}));
    assert.ok(!isValidMessage({role: 'user', content: 123}));
});
