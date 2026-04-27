import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { IndexPage } from '../../../priorities/assets/js/pages/IndexPage';

vi.mock('../../../priorities/assets/js/api', () => ({
  createLobby: vi.fn(),
  joinLobby:   vi.fn(),
}));

import * as api from '../../../priorities/assets/js/api';
const mockCreateLobby = vi.mocked(api.createLobby);
const mockJoinLobby   = vi.mocked(api.joinLobby);

// Stub window.location.href assignment
const locationSpy = vi.spyOn(window, 'location', 'get');
let hrefAssigned = '';
let locationSearch = '';
beforeAll(() => {
  locationSpy.mockReturnValue({
    ...window.location,
    get search() { return locationSearch; },
    get href() { return hrefAssigned; },
    set href(v: string) { hrefAssigned = v; },
  } as Location);
});

describe('IndexPage', () => {
  beforeEach(() => {
    hrefAssigned = '';
    locationSearch = '';
    mockCreateLobby.mockReset();
    mockJoinLobby.mockReset();
  });

  function tabs() {
    return within(document.querySelector('.tabs')!);
  }
  function form() {
    return within(document.querySelector('.lobby-form')!);
  }

  it('shows Create Lobby tab by default', () => {
    render(<IndexPage />);
    expect(tabs().getByRole('button', { name: 'Create Lobby' })).toHaveClass('active');
  });

  it('switches to Join Lobby tab on click', async () => {
    const user = userEvent.setup();
    render(<IndexPage />);
    await user.click(tabs().getByRole('button', { name: 'Join Lobby' }));
    expect(tabs().getByRole('button', { name: 'Join Lobby' })).toHaveClass('active');
    expect(screen.getByLabelText(/Lobby code/i)).toBeInTheDocument();
  });

  it('shows only name field on Create tab', () => {
    render(<IndexPage />);
    expect(screen.getByLabelText(/Your name/i)).toBeInTheDocument();
    expect(screen.queryByLabelText(/Lobby code/i)).toBeNull();
  });

  describe('Create Lobby form', () => {
    it('calls createLobby with name and navigates on success', async () => {
      mockCreateLobby.mockResolvedValue({
        success: true, code: 'ABCDEF', lobby_id: 1, player_id: 10,
        redirect_url: '/priorities/lobby.php?lobby_id=1',
      });
      const user = userEvent.setup();
      render(<IndexPage />);
      await user.type(screen.getByLabelText(/Your name/i), 'Alice');
      await user.click(form().getByRole('button', { name: 'Create Lobby' }));
      await waitFor(() => expect(mockCreateLobby).toHaveBeenCalledWith('Alice', true, 60, undefined));
      expect(hrefAssigned).toBe('/priorities/lobby.php?lobby_id=1');
    });

    it('shows loading state while creating', async () => {
      mockCreateLobby.mockReturnValue(new Promise(() => {}));
      const user = userEvent.setup();
      render(<IndexPage />);
      await user.type(screen.getByLabelText(/Your name/i), 'Alice');
      await user.click(form().getByRole('button', { name: 'Create Lobby' }));
      await waitFor(() => expect(form().getByRole('button', { name: 'Creating…' })).toBeDisabled());
    });

    it('shows error when createLobby fails', async () => {
      mockCreateLobby.mockRejectedValue(new Error('Name taken'));
      const user = userEvent.setup();
      render(<IndexPage />);
      await user.type(screen.getByLabelText(/Your name/i), 'Alice');
      await user.click(form().getByRole('button', { name: 'Create Lobby' }));
      await waitFor(() => expect(screen.getByText('Name taken')).toBeInTheDocument());
    });

    it('preserves dev_profile in redirect when backend omits it', async () => {
      locationSearch = '?dev_profile=alpha';
      mockCreateLobby.mockResolvedValue({
        success: true, code: 'ABCDEF', lobby_id: 1, player_id: 10,
        redirect_url: '/priorities/lobby.php?lobby_id=1',
      });

      const user = userEvent.setup();
      render(<IndexPage />);
      await user.type(screen.getByLabelText(/Your name/i), 'Alice');
      await user.click(form().getByRole('button', { name: 'Create Lobby' }));

      await waitFor(() => expect(mockCreateLobby).toHaveBeenCalledWith('Alice', true, 60, 'alpha'));
      expect(hrefAssigned).toBe('/priorities/lobby.php?lobby_id=1&dev_profile=alpha');
    });
  });

  describe('Join Lobby form', () => {
    it('calls joinLobby with name and uppercased code and navigates', async () => {
      mockJoinLobby.mockResolvedValue({
        success: true, lobby_id: 2, player_id: 20,
        redirect_url: '/priorities/lobby.php?lobby_id=2',
      });
      const user = userEvent.setup();
      render(<IndexPage />);
      await user.click(tabs().getByRole('button', { name: 'Join Lobby' }));
      await user.type(screen.getByLabelText(/Your name/i), 'Bob');
      await user.type(screen.getByLabelText(/Lobby code/i), 'abcdef');
      await user.click(form().getByRole('button', { name: 'Join Lobby' }));
      await waitFor(() => expect(mockJoinLobby).toHaveBeenCalledWith('Bob', 'ABCDEF', undefined));
      expect(hrefAssigned).toBe('/priorities/lobby.php?lobby_id=2');
    });

    it('shows error when joinLobby fails', async () => {
      mockJoinLobby.mockRejectedValue(new Error('Lobby not found'));
      const user = userEvent.setup();
      render(<IndexPage />);
      await user.click(tabs().getByRole('button', { name: 'Join Lobby' }));
      await user.type(screen.getByLabelText(/Your name/i), 'Bob');
      await user.type(screen.getByLabelText(/Lobby code/i), 'ZZZZZZ');
      await user.click(form().getByRole('button', { name: 'Join Lobby' }));
      await waitFor(() => expect(screen.getByText('Lobby not found')).toBeInTheDocument());
    });

    it('shows loading state while joining', async () => {
      mockJoinLobby.mockReturnValue(new Promise(() => {}));
      const user = userEvent.setup();
      render(<IndexPage />);
      await user.click(tabs().getByRole('button', { name: 'Join Lobby' }));
      await user.type(screen.getByLabelText(/Your name/i), 'Bob');
      await user.type(screen.getByLabelText(/Lobby code/i), 'ABCDEF');
      await user.click(form().getByRole('button', { name: 'Join Lobby' }));
      await waitFor(() => expect(form().getByRole('button', { name: 'Joining…' })).toBeDisabled());
    });

    it('preserves dev_profile in join redirect when backend omits it', async () => {
      locationSearch = '?dev_profile=alpha';
      mockJoinLobby.mockResolvedValue({
        success: true, lobby_id: 2, player_id: 20,
        redirect_url: '/priorities/lobby.php?lobby_id=2',
      });

      const user = userEvent.setup();
      render(<IndexPage />);
      await user.click(tabs().getByRole('button', { name: 'Join Lobby' }));
      await user.type(screen.getByLabelText(/Your name/i), 'Bob');
      await user.type(screen.getByLabelText(/Lobby code/i), 'abcdef');
      await user.click(form().getByRole('button', { name: 'Join Lobby' }));

      await waitFor(() => expect(mockJoinLobby).toHaveBeenCalledWith('Bob', 'ABCDEF', 'alpha'));
      expect(hrefAssigned).toBe('/priorities/lobby.php?lobby_id=2&dev_profile=alpha');
    });
  });
});
