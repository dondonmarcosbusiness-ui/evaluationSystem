/**
 * Shared flag that tells useBootstrapModal's hidden-event sync to stand down.
 *
 * The global confirm dialog (see useConfirm.js) temporarily hides any open
 * Bootstrap modals while it asks the question, then re-shows them. Without
 * this guard, those programmatic hides would flip each modal's bound boolean
 * to false and desync Vue state from the visible UI.
 */
export const modalSyncState = { suspended: false };
