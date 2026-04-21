import { render, screen } from '@testing-library/react';
import { CardList } from '../../../priorities/assets/js/components/CardList';
import { cards, scores } from '../fixtures';

describe('CardList', () => {
  describe('static (non-draggable) mode', () => {
    it('renders all cards', () => {
      render(<CardList cards={cards} />);
      expect(screen.getByText('Pizza')).toBeInTheDocument();
      expect(screen.getByText('Rain')).toBeInTheDocument();
      expect(screen.getByText('Ice cream')).toBeInTheDocument();
    });

    it('renders card position numbers starting at 1', () => {
      render(<CardList cards={cards} />);
      const positions = document.querySelectorAll('.card-position');
      expect(positions[0]).toHaveTextContent('1');
      expect(positions[1]).toHaveTextContent('2');
      expect(positions[2]).toHaveTextContent('3');
    });

    it('renders card emoji', () => {
      render(<CardList cards={cards} />);
      expect(screen.getByText('🍕')).toBeInTheDocument();
    });

    it('cards are not draggable by default', () => {
      render(<CardList cards={cards} />);
      const items = document.querySelectorAll('.card-item');
      items.forEach(item => expect(item).not.toHaveClass('draggable'));
    });
  });

  describe('draggable mode', () => {
    it('applies draggable class to cards', () => {
      render(<CardList cards={cards} draggable />);
      const items = document.querySelectorAll('.card-item');
      items.forEach(item => expect(item).toHaveClass('draggable'));
    });
  });

  describe('result annotations', () => {
    it('applies correct class to cards with correct result', () => {
      render(<CardList cards={cards} results={scores} />);
      const items = Array.from(document.querySelectorAll('.card-item'));
      expect(items[0]).toHaveClass('correct');   // card 1 correct
      expect(items[1]).toHaveClass('incorrect'); // card 2 incorrect
      expect(items[2]).toHaveClass('correct');   // card 3 correct
    });

    it('shows result icon for correct card', () => {
      render(<CardList cards={cards} results={scores} />);
      const icons = document.querySelectorAll('.card-result-icon');
      expect(icons[0]).toHaveTextContent('✓');
      expect(icons[1]).toHaveTextContent('✗');
    });

    it('does not show result icons when no results provided', () => {
      render(<CardList cards={cards} />);
      expect(document.querySelectorAll('.card-result-icon')).toHaveLength(0);
    });
  });

  describe('empty state', () => {
    it('renders an empty list with no cards', () => {
      render(<CardList cards={[]} />);
      expect(document.querySelectorAll('.card-item')).toHaveLength(0);
    });
  });
});
