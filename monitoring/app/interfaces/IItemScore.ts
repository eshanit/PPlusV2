export interface IItemScore {
  itemSlug: string
  menteeScore: 1 | 2 | 3 | 4 | 5 | null  // null = N/A
  notes?: string  // optional notes per item
}
