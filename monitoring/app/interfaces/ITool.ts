import type { IEvalItem } from './IEvalItem'

export interface ITool {
  slug: string
  label: string
  items: IEvalItem[]
}
