import { useEffect, useRef, useState } from 'react';
import type { StreamState } from '../types';

export function useSSE(lobbyId: number, initialVersion: number) {
  const [state, setState] = useState<StreamState | null>(null);
  const versionRef = useRef<number>(initialVersion);

  useEffect(() => {
    let es: EventSource | null = null;
    let cancelled = false;

    function connect() {
      if (cancelled) return;
      const url = `api/stream.php?lobby_id=${lobbyId}&state_version=${versionRef.current}`;
      es = new EventSource(url);

      es.onmessage = (event: MessageEvent) => {
        const data = JSON.parse(event.data) as StreamState;
        versionRef.current = data.state_version;
        setState(data);
      };

      es.onerror = () => {
        es?.close();
        if (!cancelled) {
          // EventSource reconnects automatically but we manage our own to
          // pass the latest version in the query string.
          setTimeout(connect, 500);
        }
      };
    }

    connect();

    return () => {
      cancelled = true;
      es?.close();
    };
  }, [lobbyId]);

  return { state, version: versionRef.current };
}
