import api from "../services/api.js";

/**
 * Single source of truth for semester / academic-year options.
 *
 * Admins manage these lists in System Settings (stored as the
 * `semester_options` / `academic_year_options` settings keys, plus
 * `archived_semester_options` / `archived_academic_year_options` for values
 * retired from active dropdowns but kept reviewable via history). Every select
 * in the app reads from here — nothing is hardcoded in components.
 */

export const DEFAULT_SEMESTERS = ["1st Semester", "2nd Semester", "Summer"];

export function defaultAcademicYears(count = 5) {
  const y = new Date().getFullYear();
  return Array.from({ length: count }, (_, i) => `${y - i}-${y - i + 1}`);
}

export function asStringArray(value, fallback = []) {
  if (Array.isArray(value)) return value.filter((v) => typeof v === "string" && v.trim()).map((v) => v.trim());
  if (typeof value === "string" && value.trim()) {
    try {
      const parsed = JSON.parse(value);
      if (Array.isArray(parsed)) return asStringArray(parsed);
    } catch {
      /* not JSON — treat as a single entry */
    }
    return [value.trim()];
  }
  return [...fallback];
}

let cache = null;

/** { semesters: string[], years: string[], archivedSemesters: string[], archivedYears: string[] } — cached per page load. */
export function fetchAcademicConfig(force = false) {
  if (cache && !force) return cache;
  cache = api
    .get("/settings")
    .then((res) => {
      const d = res.data || {};
      return {
        semesters: asStringArray(d.semester_options, DEFAULT_SEMESTERS),
        years: asStringArray(d.academic_year_options, defaultAcademicYears()),
        archivedSemesters: asStringArray(d.archived_semester_options, []),
        archivedYears: asStringArray(d.archived_academic_year_options, []),
      };
    })
    .catch(() => ({
      semesters: [...DEFAULT_SEMESTERS],
      years: defaultAcademicYears(),
      archivedSemesters: [],
      archivedYears: [],
    }));
  // Never leave a rejected promise cached.
  cache.catch(() => {
    cache = null;
  });
  return cache;
}

export function clearAcademicCache() {
  cache = null;
  periodsCache = null;
}

/**
 * Single source of truth for department dropdown options: the Course List.
 * Derives the unique sorted departments from GET /courses plus the
 * system-wide "General Education" bucket (same convention as Faculty and
 * Assignment Management). Faculty records carry free-text departments that
 * can go stale (e.g. departments whose courses were deleted), so filters
 * must never be built from faculty data.
 */
export function courseDepartments(courses) {
  const depts = (courses || [])
    .map((c) => c?.department)
    .filter((d) => typeof d === "string" && d.trim())
    .map((d) => d.trim());
  const unique = [...new Set(depts)];
  if (!unique.includes("General Education")) unique.push("General Education");
  return unique.sort();
}

let periodsCache = null;

/**
 * Union of configured, archived, and actually-used periods (GET /reports/periods).
 * Used by the archive page and Settings "in use" counts so a value removed
 * from the active dropdowns can still be reviewed through history.
 */
export function fetchAcademicPeriods(force = false) {
  if (periodsCache && !force) return periodsCache;
  periodsCache = api
    .get("/reports/periods")
    .then((res) => {
      const d = res.data || {};
      return {
        activeSemester: d.active_semester || "",
        activeAcademicYear: d.active_academic_year || "",
        semesters: asStringArray(d.semesters, DEFAULT_SEMESTERS),
        years: asStringArray(d.academic_years, defaultAcademicYears()),
        archivedSemesters: asStringArray(d.archived_semesters, []),
        archivedYears: asStringArray(d.archived_academic_years, []),
        usedPeriods: Array.isArray(d.used_periods) ? d.used_periods : [],
        semesterUsage: d.semester_usage || {},
        academicYearUsage: d.academic_year_usage || {},
      };
    })
    .catch(() => ({
      activeSemester: "",
      activeAcademicYear: "",
      semesters: [...DEFAULT_SEMESTERS],
      years: defaultAcademicYears(),
      archivedSemesters: [],
      archivedYears: [],
      usedPeriods: [],
      semesterUsage: {},
      academicYearUsage: {},
    }));
  periodsCache.catch(() => {
    periodsCache = null;
  });
  return periodsCache;
}
