import { useState, useCallback } from 'react';
import {
  DndContext,
  closestCenter,
  PointerSensor,
  TouchSensor,
  useSensor,
  useSensors,
  DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  useSortable,
  verticalListSortingStrategy,
  arrayMove,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import type { CardState, ScoreResultState } from '../types';

// ── Individual sortable card item ─────────────────────────────────────────────

interface CardItemProps {
  card: CardState;
  index: number;
  draggable: boolean;
  result?: ScoreResultState;
}

function CardItem({ card, index, draggable, result }: CardItemProps) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } =
    useSortable({ id: card.id, disabled: !draggable });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  let resultClass = '';
  if (result !== undefined) {
    resultClass = result.correct ? ' correct' : ' incorrect';
  }

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`card-item${draggable ? ' draggable' : ''}${resultClass}`}
      {...(draggable ? { ...attributes, ...listeners } : {})}
    >
      <span className="card-position">{index + 1}</span>
      <span className="card-emoji">{card.emoji}</span>
      <span className="card-content">{card.content}</span>
      {result !== undefined && (
        <span className="card-result-icon">{result.correct ? '✓' : '✗'}</span>
      )}
    </div>
  );
}

// ── CardList ──────────────────────────────────────────────────────────────────

interface Props {
  cards: CardState[];
  draggable?: boolean;
  results?: ScoreResultState[];
  /** Called with the new ordered card ID array when user reorders. */
  onReorder?: (orderedIds: number[]) => void;
}

export function CardList({ cards, draggable = false, results, onReorder }: Props) {
  const [items, setItems] = useState<CardState[]>(cards);

  // Keep in sync when parent updates (e.g. SSE push).
  // Only re-sync if the IDs changed (new round) to avoid clobbering in-progress drags.
  const itemIds = items.map(c => c.id).join(',');
  const cardIds = cards.map(c => c.id).join(',');
  if (itemIds !== cardIds) {
    setItems(cards);
  }

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(TouchSensor,   { activationConstraint: { delay: 150, tolerance: 5 } }),
  );

  const handleDragEnd = useCallback((event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;

    setItems(prev => {
      const oldIndex = prev.findIndex(c => c.id === active.id);
      const newIndex = prev.findIndex(c => c.id === over.id);
      const next = arrayMove(prev, oldIndex, newIndex);
      onReorder?.(next.map(c => c.id));
      return next;
    });
  }, [onReorder]);

  const resultMap = new Map<number, ScoreResultState>();
  results?.forEach(r => resultMap.set(r.card_id, r));

  if (!draggable) {
    return (
      <div className="card-list">
        {items.map((card, idx) => (
          <CardItem
            key={card.id}
            card={card}
            index={idx}
            draggable={false}
            result={resultMap.get(card.id)}
          />
        ))}
      </div>
    );
  }

  return (
    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
      <SortableContext items={items.map(c => c.id)} strategy={verticalListSortingStrategy}>
        <div className="card-list">
          {items.map((card, idx) => (
            <CardItem
              key={card.id}
              card={card}
              index={idx}
              draggable
              result={resultMap.get(card.id)}
            />
          ))}
        </div>
      </SortableContext>
    </DndContext>
  );
}
