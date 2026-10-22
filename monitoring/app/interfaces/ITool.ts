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
  /**
   * Whether sessions with this tool also evaluate the general counselling
   * competencies (DC1-DC9). Defaults to true. Echocardiogram turns it off at
   * the client's request (2026-10-22).
   */
  includesCounselling?: boolean
}
