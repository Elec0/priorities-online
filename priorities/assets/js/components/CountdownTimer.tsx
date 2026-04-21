import { useState, useEffect, useRef } from 'react';

interface Props {
  deadline: string | null;
}

export function CountdownTimer({ deadline }: Props) {
  const [remaining, setRemaining] = useState<number | null>(null);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    if (!deadline) {
      setRemaining(null);
      return;
    }

    const deadlineMs = new Date(deadline.endsWith('Z') ? deadline : deadline + 'Z').getTime();

    function tick() {
      const secs = Math.max(0, Math.round((deadlineMs - Date.now()) / 1000));
      setRemaining(secs);
      if (secs <= 0 && intervalRef.current !== null) {
        clearInterval(intervalRef.current);
      }
    }

    tick();
    intervalRef.current = setInterval(tick, 1000);

    return () => {
      if (intervalRef.current !== null) clearInterval(intervalRef.current);
    };
  }, [deadline]);

  if (remaining === null) return null;

  const warning = remaining < 15;

  return (
    <div className={`countdown${warning ? ' countdown-warning' : ''}`}>
      ⏱ {remaining}s remaining
    </div>
  );
}
