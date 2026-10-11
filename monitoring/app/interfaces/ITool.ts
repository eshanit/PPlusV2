import type { IEvalItem } from './IEvalItem'

export interface IItemGroup {
  slug: string
  label: string
  itemSlugs: string[]
}

export interface IToolTheme {
  primary: string
  headerBg: string
  headerText: string
  accent: string
}

export interface ITool {
  slug: string
  label: string
  items: IEvalItem[]
  groups?: IItemGroup[]
  theme?: IToolTheme
}
