// Lightweight embedded user snapshot stored inside session documents.
// Denormalised intentionally — preserves who evaluated at the time of the session
// even if the user record is later updated.
export interface IUserRef {
  id: string
  firstname: string
  lastname: string
  username: string
}
