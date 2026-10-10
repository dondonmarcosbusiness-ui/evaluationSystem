import { ref } from "vue";
import api from "../services/api.js";

/**
 * Evaluation window state shared by the dashboard CTA, the sidebar link, the
 * router guard and the evaluation form, so "is the window open?" is decided in
 * exactly one place on the client.
 *
 * Fail-closed: only an explicit "open" from the server counts as open — an
 * error, a timeout or a missing value all keep students out of the flow. The
 * server enforces the same rule on every request, this only stops the UI from
 * leading students somewhere the API will refuse.
 */
export const evaluationStatus = ref("closed");

const TTL_MS = 30000;
let loadedAt = 0;
let inflight = null;

/** Record a status that was already fetched as part of another request. */
export function applyEvaluationStatus(value) {
  evaluationStatus.value = value === "open" ? "open" : "closed";
  loadedAt = Date.now();
}

/**
 * Refresh the shared status (throttled unless `force`).
 *
 * @param {boolean} force skip the TTL and hit the API now
 * @returns {Promise<"open" | "closed">}
 */
export function refreshEvaluationWindow(force = false) {
  if (!inflight && !force && Date.now() - loadedAt < TTL_MS) {
    return Promise.resolve(evaluationStatus.value);
  }

  if (!inflight) {
    inflight = api
      .get("/settings")
      .then((res) => applyEvaluationStatus(res.data?.evaluation_status))
      .catch(() => {
        evaluationStatus.value = "closed";
      })
      .finally(() => {
        loadedAt = Date.now();
        inflight = null;
      });
  }

  return inflight.then(() => evaluationStatus.value);
}

/** @returns {Promise<boolean>} */
export async function isEvaluationOpen() {
  return (await refreshEvaluationWindow()) === "open";
}
