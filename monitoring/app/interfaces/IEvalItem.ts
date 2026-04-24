export interface IEvalItem {
  // Globally unique across all tools: '{toolSlug}-{number}' e.g. 'diabetes-D1', 'echo-E1'
  slug: string
  // Display code as it appears in the paper tool (NOT globally unique — e.g. both echo
  // and epilepsy have 'E1'. Always display in the context of the parent tool.)
  number: string
  title: string
  category: string
  // Grey items in the paper tool — advanced competencies not required for basic level
  isAdvanced: boolean
}
