import api from "../services/api.js";

/**
 * Download a CSV produced by the API and hand it to the browser.
 * Throws with the server's message when the response isn't a CSV
 * (e.g. an error payload that came back as JSON).
 */
export async function downloadCsv(url, params = {}, fallbackName = "export.csv") {
  const res = await api.get(url, { params, responseType: "blob" });
  const blob = res.data;
  const type = String(res.headers?.["content-type"] || blob?.type || "");

  if (!type.includes("csv")) {
    let message = "Export failed. Please try again.";
    try {
      const payload = JSON.parse(await blob.text());
      if (payload?.message) message = payload.message;
    } catch {
      /* not JSON — keep the generic message */
    }
    throw new Error(message);
  }

  saveBlob(blob, filenameFrom(res.headers?.["content-disposition"]) || fallbackName);
}

function filenameFrom(disposition = "") {
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition);
  return match ? decodeURIComponent(match[1]) : "";
}

function saveBlob(blob, filename) {
  const href = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = href;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(href), 1000);
}
