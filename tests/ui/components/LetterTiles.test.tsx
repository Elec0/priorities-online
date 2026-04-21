import { render, screen } from '@testing-library/react';
import { LetterTiles } from '../../../priorities/assets/js/components/LetterTiles';
import type { LetterMapState } from '../../../priorities/assets/js/types';

const empty: LetterMapState = { P: 0, R: 0, I: 0, O: 0, T: 0, E: 0, S: 0 };
const full: LetterMapState  = { P: 1, R: 2, I: 3, O: 1, T: 1, E: 1, S: 1 };

describe('LetterTiles', () => {
  it('renders the label', () => {
    render(<LetterTiles label="Player 1" letters={empty} />);
    expect(screen.getByText('Player 1')).toBeInTheDocument();
  });

  it('renders 10 tiles for the PRIORITIES sequence', () => {
    render(<LetterTiles label="Test" letters={empty} />);
    const tiles = document.querySelectorAll('.tile');
    expect(tiles).toHaveLength(10);
  });

  it('marks all tiles hollow when letters are all 0', () => {
    render(<LetterTiles label="Test" letters={empty} />);
    const tiles = document.querySelectorAll('.tile');
    tiles.forEach(tile => expect(tile).toHaveClass('hollow'));
  });

  it('marks all tiles filled when all thresholds are met', () => {
    render(<LetterTiles label="Test" letters={full} />);
    const tiles = document.querySelectorAll('.tile');
    tiles.forEach(tile => expect(tile).toHaveClass('filled'));
  });

  it('correctly fills only the earned tiles (partial)', () => {
    // P=1, R=1 fills tiles 0 (P≥1) and 1 (R≥1) but not tile 4 (R≥2)
    const partial: LetterMapState = { P: 1, R: 1, I: 0, O: 0, T: 0, E: 0, S: 0 };
    render(<LetterTiles label="Test" letters={partial} />);
    const tiles = Array.from(document.querySelectorAll('.tile'));

    // Sequence: P R I O R I T I E S
    expect(tiles[0]).toHaveClass('filled');  // P≥1 ✓
    expect(tiles[1]).toHaveClass('filled');  // R≥1 ✓
    expect(tiles[2]).toHaveClass('hollow');  // I≥1 ✗
    expect(tiles[4]).toHaveClass('hollow');  // R≥2 ✗
  });

  it('sets aria-label correctly for filled and hollow tiles', () => {
    const partial: LetterMapState = { P: 1, R: 0, I: 0, O: 0, T: 0, E: 0, S: 0 };
    render(<LetterTiles label="Test" letters={partial} />);
    expect(screen.getByLabelText('P collected')).toBeInTheDocument();
    // R appears twice in the sequence (R≥1 and R≥2), both hollow
    const rTiles = screen.getAllByLabelText('R empty');
    expect(rTiles).toHaveLength(2);
  });
});
