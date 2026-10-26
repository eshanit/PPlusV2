/** Why a competency can't be assessed at the mentee's facility. See NOT_ASSESSABLE.md. */
export type NotAssessableReason =
  | 'medicine_unavailable'
  | 'lab_test_unavailable'
  | 'equipment_unavailable'
  | 'referral_unavailable'
  | 'service_not_offered'
  | 'patient_group_not_seen'
  | 'not_locally_relevant'
  | 'outside_scope_of_practice'
  | 'other'

export interface INotAssessable {
  reason: NotAssessableReason
  /** Optional, except required for 'other'. */
  note?: string
}

export interface IItemScore {
  itemSlug: string
  menteeScore: 1 | 2 | 3 | 4 | 5 | null  // null = N/A, or not assessable when notAssessable is set
  notes?: string  // optional notes per item
  // The item can't be assessed at this facility. Set with menteeScore null. It
  // takes the item out of the competency requirement until a later score
  // replaces it.
  notAssessable?: INotAssessable
}
