import { useState, FormEvent, useEffect, useRef } from 'react';
import { sendMessage } from '../api';
import type { ChatMessage } from '../types';

interface Props {
  chat: ChatMessage[];
  playerId: number;
}

export function ChatPanel({ chat, playerId }: Props) {
  const [text, setText]       = useState('');
  const [error, setError]     = useState('');
  const [sending, setSending] = useState(false);
  const bottomRef             = useRef<HTMLDivElement>(null);

  // Auto-scroll to bottom when new messages arrive.
  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [chat.length]);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    if (text.trim() === '') return;
    setError('');
    setSending(true);
    try {
      await sendMessage(text.trim());
      setText('');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to send');
    } finally {
      setSending(false);
    }
  }

  return (
    <div className="chat-panel">
      <div className="chat-messages">
        {chat.map(msg => (
          <div
            key={msg.id}
            className={`chat-message${msg.player_id === null ? ' system' : ''}${msg.player_id === playerId ? ' own' : ''}`}
          >
            {msg.player_id !== null && (
              <span className="chat-author">{msg.player_name}: </span>
            )}
            <span className="chat-text">{msg.message}</span>
          </div>
        ))}
        <div ref={bottomRef} />
      </div>
      {error && <p className="error-msg">{error}</p>}
      <form onSubmit={handleSubmit} className="chat-form">
        <input
          type="text"
          value={text}
          maxLength={256}
          placeholder="Say something…"
          onChange={e => setText(e.target.value)}
          disabled={sending}
        />
        <button type="submit" disabled={sending || text.trim() === ''}>
          Send
        </button>
      </form>
    </div>
  );
}
