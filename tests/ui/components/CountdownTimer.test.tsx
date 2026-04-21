import { render, screen, act } from '@testing-library/react';
import { CountdownTimer } from '../../../priorities/assets/js/components/CountdownTimer';

describe('CountdownTimer', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('renders nothing when deadline is null', () => {
    const { container } = render(<CountdownTimer deadline={null} />);
    expect(container.firstChild).toBeNull();
  });

  it('renders the remaining seconds', () => {
    const deadline = new Date(Date.now() + 30_000).toISOString();
    render(<CountdownTimer deadline={deadline} />);
    expect(screen.getByText(/30s remaining/)).toBeInTheDocument();
  });

  it('applies warning class when under 15 seconds', () => {
    const deadline = new Date(Date.now() + 10_000).toISOString();
    render(<CountdownTimer deadline={deadline} />);
    const el = screen.getByText(/10s remaining/);
    expect(el.closest('.countdown')).toHaveClass('countdown-warning');
  });

  it('does not apply warning class above 15 seconds', () => {
    const deadline = new Date(Date.now() + 20_000).toISOString();
    render(<CountdownTimer deadline={deadline} />);
    const el = screen.getByText(/20s remaining/);
    expect(el.closest('.countdown')).not.toHaveClass('countdown-warning');
  });

  it('ticks down over time', () => {
    const deadline = new Date(Date.now() + 5_000).toISOString();
    render(<CountdownTimer deadline={deadline} />);
    expect(screen.getByText(/5s remaining/)).toBeInTheDocument();

    act(() => { vi.advanceTimersByTime(2_000); });
    expect(screen.getByText(/3s remaining/)).toBeInTheDocument();
  });

  it('clamps at 0 seconds', () => {
    const deadline = new Date(Date.now() + 1_000).toISOString();
    render(<CountdownTimer deadline={deadline} />);

    act(() => { vi.advanceTimersByTime(5_000); });
    expect(screen.getByText(/0s remaining/)).toBeInTheDocument();
  });
});
