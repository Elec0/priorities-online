import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ChatPanel } from '../../../priorities/assets/js/components/ChatPanel';
import { chatMessages, playerAlice, playerBob } from '../fixtures';

vi.mock('../../../priorities/assets/js/api', () => ({
  sendMessage: vi.fn(),
}));

import * as api from '../../../priorities/assets/js/api';
const mockSendMessage = vi.mocked(api.sendMessage);

describe('ChatPanel', () => {
  beforeEach(() => {
    mockSendMessage.mockReset();
  });

  it('renders all chat messages', () => {
    render(<ChatPanel chat={chatMessages} playerId={playerAlice.id} />);
    expect(screen.getByText('Hello!')).toBeInTheDocument();
    expect(screen.getByText('Hey there')).toBeInTheDocument();
    expect(screen.getByText('Game started')).toBeInTheDocument();
  });

  it('shows author name for player messages', () => {
    render(<ChatPanel chat={chatMessages} playerId={playerAlice.id} />);
    expect(screen.getByText('Alice:')).toBeInTheDocument();
    expect(screen.getByText('Bob:')).toBeInTheDocument();
  });

  it('does not show author name for system messages', () => {
    render(<ChatPanel chat={chatMessages} playerId={playerAlice.id} />);
    const systemMsg = screen.getByText('Game started').closest('.chat-message');
    expect(systemMsg).toHaveClass('system');
    expect(systemMsg?.querySelector('.chat-author')).toBeNull();
  });

  it('applies own class to current player messages', () => {
    render(<ChatPanel chat={chatMessages} playerId={playerAlice.id} />);
    const aliceMsg = screen.getByText('Hello!').closest('.chat-message');
    expect(aliceMsg).toHaveClass('own');
    const bobMsg = screen.getByText('Hey there').closest('.chat-message');
    expect(bobMsg).not.toHaveClass('own');
  });

  it('sends message and clears input on submit', async () => {
    mockSendMessage.mockResolvedValue({ success: true });
    const user = userEvent.setup();
    render(<ChatPanel chat={[]} playerId={playerAlice.id} />);

    const input = screen.getByPlaceholderText('Say something…');
    await user.type(input, 'Nice game!');
    await user.click(screen.getByRole('button', { name: 'Send' }));

    await waitFor(() => expect(mockSendMessage).toHaveBeenCalledWith('Nice game!'));
    await waitFor(() => expect(input).toHaveValue(''));
  });

  it('disables send button when input is empty', () => {
    render(<ChatPanel chat={[]} playerId={playerAlice.id} />);
    expect(screen.getByRole('button', { name: 'Send' })).toBeDisabled();
  });

  it('does not submit if input is only whitespace', async () => {
    const user = userEvent.setup();
    render(<ChatPanel chat={[]} playerId={playerAlice.id} />);
    const input = screen.getByPlaceholderText('Say something…');
    await user.type(input, '   ');
    await user.click(screen.getByRole('button', { name: 'Send' }));
    expect(mockSendMessage).not.toHaveBeenCalled();
  });

  it('shows error message when send fails', async () => {
    mockSendMessage.mockRejectedValue(new Error('Network error'));
    const user = userEvent.setup();
    render(<ChatPanel chat={[]} playerId={playerBob.id} />);
    await user.type(screen.getByPlaceholderText('Say something…'), 'Hi');
    await user.click(screen.getByRole('button', { name: 'Send' }));
    await waitFor(() => expect(screen.getByText('Network error')).toBeInTheDocument());
  });

  it('renders empty chat with no messages', () => {
    render(<ChatPanel chat={[]} playerId={playerAlice.id} />);
    expect(document.querySelectorAll('.chat-message')).toHaveLength(0);
  });
});
