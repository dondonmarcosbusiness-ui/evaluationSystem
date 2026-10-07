<template>
  <div class="d-flex">
    <Sidebar />
    <div class="main-wrapper w-100">
      <Navbar>
        <template #title>{{ t.dashboard }}</template>
      </Navbar>

      <div class="content-area">
        <!-- Admin/Stats Dashboard -->
        <template v-if="dashboardMode === 'summary'">
          <!-- Admin dashboard header -->
          <div
            class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-3 fade-in-up"
          >
            <div class="d-flex align-items-center gap-2">
              <h4 class="mb-0 fw-800 text-main">Faculty Evaluation Summary</h4>
            </div>
          </div>

          <!-- Summary status (avg rating · key totals · live status list) -->
          <div class="row g-4 fade-in-up">
            <!-- Avg Rating (hero) -->
            <div class="col-lg-5 d-flex">
              <div class="card h-100 flex-grow-1 summary-hero">
                <div class="card-body">
                  <div class="summary-hero-head">
                    <div class="hero-icon"><i class="fas fa-star"></i></div>
                    <div class="hero-head-text">
                      <div class="hero-eyebrow">Avg Rating</div>
                      <div class="hero-sub">across {{ stats.total_evaluations || 0 }} evaluations</div>
                    </div>
                  </div>
                  <div class="summary-hero-value">
                    {{ Number(stats.average_rating).toFixed(2) }}
                    <span class="summary-hero-scale">/ 5</span>
                  </div>
                  <div class="rating-mini">
                    <div v-for="(count, i) in stats.rating_distribution || []" :key="i" class="rating-mini-row">
                      <span class="rating-mini-star">
                        <i class="fas fa-star"></i>
                        {{ 5 - i }}
                      </span>
                      <div class="rating-mini-track">
                        <div class="rating-mini-fill" :style="{ width: ratingBarWidth(count) }"></div>
                      </div>
                      <span class="rating-mini-count">{{ count }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Key totals (stacked) -->
            <div class="col-lg-3 d-flex flex-column gap-3">
              <div class="card summary-mini flex-grow-1">
                <div class="mini-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="mini-body">
                  <div class="mini-label">Total Student</div>
                  <div class="mini-value">{{ stats.total_students }}</div>
                </div>
              </div>
              <div class="card summary-mini flex-grow-1">
                <div class="mini-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="mini-body">
                  <div class="mini-label">Total Faculty</div>
                  <div class="mini-value">{{ stats.total_faculty }}</div>
                </div>
              </div>
              <div class="card summary-mini flex-grow-1">
                <div class="mini-icon"><i class="fas fa-arrow-trend-up"></i></div>
                <div class="mini-body">
                  <div class="mini-label">Peak Online Today</div>
                  <div class="mini-value">{{ onlineStudents.peak_today || 0 }}</div>
                </div>
              </div>
            </div>

            <!-- Status list -->
            <div class="col-lg-4 d-flex">
              <div class="card h-100 flex-grow-1 summary-status">
                <div class="card-header d-flex align-items-center justify-content-between">
                  <span>
                    <i class="fas fa-wave-square me-2"></i>
                    Status Overview
                  </span>
                  <span class="live-badge">
                    <span class="live-dot"></span>
                    Live
                  </span>
                </div>
                <div class="card-body status-body">
                  <div
                    v-for="(item, idx) in statusItems"
                    :key="item.label"
                    class="status-item"
                    :class="{ 'has-divider': idx > 0 }"
                  >
                    <div class="status-text">
                      <div class="status-head">
                        <span class="status-value">{{ item.value }}</span>
                        <span class="status-label">{{ item.label }}</span>
                      </div>
                      <div class="status-detail">{{ item.detail }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Students online (per department & year) + account access log -->
          <div class="row g-4 mt-1 fade-in-up">
            <div :class="$can('permission.manage') ? 'col-lg-7 d-flex' : 'col-12 d-flex'">
              <div class="card h-100 flex-grow-1">
                <div class="card-header d-flex align-items-center justify-content-between">
                  <span>
                    <i class="fas fa-user-clock me-2"></i>
                    Students Online
                  </span>
                  <span class="live-badge">
                    <span class="live-dot"></span>
                    {{ onlineStudents.total_online || 0 }} / {{ onlineStudents.total_students || 0 }} online
                  </span>
                </div>
                <div class="card-body">
                  <div class="online-hero">
                    <div class="online-hero-value">{{ onlineStudents.total_online || 0 }}</div>
                    <div class="online-hero-text">
                      <b>students active right now</b>
                      <span>within the last {{ onlineStudents.window_minutes || 5 }} minutes</span>
                    </div>
                  </div>

                  <div class="row g-3">
                    <div class="col-md-7">
                      <div class="breakdown-heading">Per department</div>
                      <div v-if="onlineByDepartment.length" class="dept-list">
                        <div v-for="row in onlineByDepartment" :key="row.department" class="dept-item">
                          <div class="breakdown-top">
                            <span class="dept-name">
                              <span class="dept-dot" :class="{ on: row.online > 0 }"></span>
                              {{ row.department }}
                            </span>
                            <span class="breakdown-count">{{ row.online }} / {{ row.total }}</span>
                          </div>
                          <div class="breakdown-track">
                            <div class="breakdown-fill" :style="{ width: onlineBar(row.online, row.total) }"></div>
                          </div>
                        </div>
                      </div>
                      <div v-else class="text-muted small">No students on record.</div>
                    </div>

                    <div class="col-md-5">
                      <div class="breakdown-heading">Per year level</div>
                      <div v-if="onlineByYear.length" class="year-grid">
                        <div
                          v-for="row in onlineByYear"
                          :key="row.year_level"
                          class="year-tile"
                          :class="{ active: row.online > 0 }"
                        >
                          <div class="year-name">{{ row.year_level }}</div>
                          <div class="year-count">
                            {{ row.online }}
                            <span>/ {{ row.total }}</span>
                          </div>
                          <div class="year-meta">online</div>
                        </div>
                      </div>
                      <div v-else class="text-muted small">No students on record.</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="$can('permission.manage')" class="col-lg-5 d-flex">
              <div class="card h-100 flex-grow-1">
                <div class="card-header">
                  <i class="fas fa-user-shield me-2"></i>
                  Account Access
                </div>
                <div class="card-body">
                  <div class="access-summary">
                    <div class="access-tile ok">
                      <div class="access-num">{{ accessSummary.success }}</div>
                      <div class="access-lab">Success</div>
                    </div>
                    <div class="access-tile bad">
                      <div class="access-num">{{ accessSummary.failed }}</div>
                      <div class="access-lab">Failed</div>
                    </div>
                    <div class="access-tile warn">
                      <div class="access-num">{{ accessSummary.locked }}</div>
                      <div class="access-lab">Locked</div>
                    </div>
                    <div class="access-tile muted">
                      <div class="access-num">{{ accessSummary.inactive }}</div>
                      <div class="access-lab">Inactive</div>
                    </div>
                  </div>

                  <div class="breakdown-heading mt-3">Recent attempts</div>
                  <div v-if="loginRows.length" class="access-list">
                    <div v-for="row in loginRows" :key="row.id" class="access-row">
                      <span class="access-status" :class="'is-' + row.status">{{ row.status }}</span>
                      <span class="access-id" :title="row.login_identifier">{{ row.login_identifier }}</span>
                      <span class="access-time">{{ formatLogTime(row.created_at) }}</span>
                    </div>
                  </div>
                  <div v-else class="text-muted small">No login attempts recorded yet.</div>

                  <div class="access-footer">
                    <button type="button" class="access-viewall" @click="openAccessLog">
                      <i class="fas fa-list-alt me-1"></i>
                      View all attempts
                      <span class="access-viewall-count">{{ accessSummary.total || 0 }}</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Full account access history, filterable by status -->
          <div ref="accessModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title fw-800 d-flex align-items-center gap-2">
                    <i class="fas fa-user-shield"></i>
                    All account access
                  </h5>
                  <button type="button" class="btn-close" @click="showAccessModal = false"></button>
                </div>
                <div class="modal-body">
                  <div class="access-toolbar">
                    <div class="access-tabs">
                      <button
                        v-for="tab in accessTabs"
                        :key="tab.key || 'all'"
                        type="button"
                        class="access-tab"
                        :class="['is-' + (tab.key || 'all'), { active: accessStatus === tab.key }]"
                        @click="setAccessStatus(tab.key)"
                      >
                        {{ tab.label }}
                        <span class="access-tab-count">{{ accessTabCount(tab.key) }}</span>
                      </button>
                    </div>
                    <label class="premium-filter-group access-search">
                      <span class="input-group-text"><i class="fas fa-search"></i></span>
                      <input
                        v-model.trim="accessSearch"
                        type="search"
                        class="form-control"
                        placeholder="Search account or IP"
                        aria-label="Search account access"
                      />
                    </label>
                  </div>

                  <div v-if="accessLoading" class="py-2">
                    <SkeletonLoader variant="list" :rows="4" />
                  </div>

                  <div v-else-if="accessRows.length" class="access-list">
                    <div v-for="row in accessRows" :key="row.id" class="access-row">
                      <span class="access-status" :class="'is-' + row.status">{{ row.status }}</span>
                      <span class="access-main">
                        <span class="access-id" :title="row.login_identifier">{{ row.login_identifier }}</span>
                        <span class="access-meta">
                          {{ row.driver || "password" }}
                          <template v-if="row.ip_address">· {{ row.ip_address }}</template>
                          <template v-if="row.reason">· {{ row.reason }}</template>
                        </span>
                      </span>
                      <span class="access-time">{{ formatLogTime(row.created_at) }}</span>
                    </div>
                  </div>

                  <div v-else class="text-muted small text-center py-4">No attempts match the selected status.</div>

                  <div v-if="!accessLoading && accessRows.length" class="access-pager">
                    <Pagination
                      :pagination="accessPagination"
                      :per-page="accessPerPage"
                      :per-page-options="[10, 25, 50, 100]"
                      @change-page="fetchAccessLogs"
                      @update:per-page="changeAccessPerPage"
                    />
                  </div>
                </div>
                <div class="modal-footer">
                  <button
                    type="button"
                    class="btn btn-outline-secondary rounded-pill px-4"
                    @click="showAccessModal = false"
                  >
                    Close
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="section-divider fade-in-up">
            <div class="section-divider-text">
              <h6 class="mb-0">Faculty Summary</h6>
              <small>Faculty evaluation performance and rating distribution</small>
            </div>
          </div>

          <div class="row g-4 mt-1 fade-in-up">
            <div class="col-lg-7 d-flex">
              <div class="card h-100 flex-grow-1">
                <div class="card-header">
                  <i class="fas fa-chart-bar"></i>
                  Performance Overview
                </div>
                <div class="card-body flex-grow-1" style="min-height: 350px">
                  <canvas id="facultyChart"></canvas>
                </div>
              </div>
            </div>
            <div class="col-lg-5 d-flex">
              <div class="card h-100 flex-grow-1">
                <div class="card-header">
                  <i class="fas fa-chart-pie"></i>
                  Rating Distribution
                </div>
                <div
                  class="card-body d-flex align-items-center justify-content-center flex-grow-1"
                  style="min-height: 350px"
                >
                  <canvas id="ratingsChart"></canvas>
                </div>
              </div>
            </div>
          </div>

          <div class="section-divider fade-in-up" v-if="$can('office.report.view')">
            <div class="section-divider-text">
              <h6 class="mb-0">Office Summary</h6>
              <small>Office feedback trends and visitor insights</small>
            </div>
          </div>

          <div class="row g-4 mt-1 fade-in-up">
            <div v-if="$can('office.report.view')" class="col-lg-8 d-flex">
              <div class="card h-100 flex-grow-1">
                <div class="card-header">
                  <i class="fas fa-chart-line"></i>
                  Office Feedback Trend
                </div>
                <div
                  class="card-body d-flex align-items-center justify-content-center flex-grow-1"
                  style="min-height: 320px"
                >
                  <canvas id="officeTrendChart"></canvas>
                </div>
              </div>
            </div>
            <div class="d-flex" :class="$can('office.report.view') ? 'col-lg-4' : 'col-lg-12'">
              <div class="card shadow-none h-100 flex-grow-1 d-flex flex-column">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                  <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-comment-dots text-primary"></i>
                    <div>
                      <h6 class="mb-0 fw-bold">Recent Feedback Feed</h6>
                      <div class="small text-muted">Faculty feedback</div>
                    </div>
                  </div>
                </div>
                <div class="card-body p-0 flex-grow-1 d-flex flex-column">
                  <div
                    class="feedback-feed flex-grow-1"
                    style="max-height: 400px; overflow-y: auto; overflow-x: hidden"
                    v-if="stats.comments && stats.comments.length > 0"
                  >
                    <div
                      v-for="comment in stats.comments.slice(0, 5)"
                      :key="comment.evaluatee_id || comment.faculty_id || comment.faculty_name"
                      class="feedback-item p-3 border-bottom hover-bg-light transition"
                    >
                      <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="faculty-info">
                          <div class="fw-bold small text-dark">{{ comment.faculty_name }}</div>
                          <div class="text-muted smallest text-uppercase fw-semibold">
                            {{ comment.subject_code || "General" }}
                          </div>
                        </div>
                        <span class="badge rounded-pill shadow-none" :class="getRatingBadgeClass(comment.rating)">
                          {{ getRatingLabel(comment.rating) }}
                        </span>
                      </div>
                      <div class="feedback-text text-secondary small line-clamp-2 mb-2">"{{ comment.text }}"</div>
                      <div class="feedback-meta d-flex justify-content-between align-items-center">
                        <span class="text-muted smallest">
                          <i class="fas fa-calendar-alt me-1"></i>
                          {{ new Date(comment.created_at).toLocaleDateString() }}
                        </span>
                        <span class="text-muted smallest">
                          <i class="fas fa-clock me-1"></i>
                          {{
                            new Date(comment.created_at).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
                          }}
                        </span>
                      </div>
                    </div>
                  </div>
                  <div v-else class="text-center py-5">
                    <div class="mb-3 opacity-25">
                      <i class="fas fa-comments fa-3x"></i>
                    </div>
                    <p class="text-muted small">No recent feedback available.</p>
                  </div>
                </div>
                <div
                  class="card-footer bg-transparent border-0 text-end py-3"
                  v-if="stats.comments && stats.comments.length > 0"
                >
                  <router-link
                    :to="{ path: '/feedbacks', query: { type: activeTab } }"
                    class="btn btn-link btn-sm p-0 fw-bold text-decoration-none"
                  >
                    View All Feedback
                    <i class="fas fa-arrow-right ms-1"></i>
                  </router-link>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-4 mt-1 fade-in-up">
            <div class="col-12 d-flex">
              <div class="card h-100 flex-grow-1">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <span>
                    <i class="fas fa-users"></i>
                    Visitor Type Breakdown
                  </span>
                  <span class="small text-muted fw-normal">{{ heatmapTotal }} visits in the last 6 months</span>
                </div>
                <div class="card-body">
                  <div v-if="heatmapHasData" class="visitor-heatmap-table-wrap">
                    <table class="visitor-heatmap-table">
                      <thead>
                        <tr>
                          <th class="heat-year-header">Year</th>
                          <th v-for="m in heatmapMonthLabels" :key="m">{{ m }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="row in heatmapTableRows" :key="row.year">
                          <td class="heat-year-cell">{{ row.year }}</td>
                          <td
                            v-for="(cell, mi) in row.months"
                            :key="mi"
                            class="heat-month-cell"
                            :class="cell.level"
                            :title="cell.tooltip"
                          >
                            <span class="heat-month-value">{{ cell.count }}</span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div v-else class="text-center py-5">
                    <div class="mb-3 opacity-25">
                      <i class="fas fa-users fa-3x"></i>
                    </div>
                    <p class="text-muted small">No visitor data yet.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- Student Dashboard -->
        <template v-if="dashboardMode === 'student'">
          <div class="card border-0 shadow-none mb-4">
            <div class="card-body text-center py-5">
              <div class="mb-4">
                <img
                  src="/assets/img/neust_logo.webp"
                  alt="NEUST Logo"
                  style="width: 120px; height: 120px; object-fit: contain"
                />
              </div>
              <h4 class="mt-3 fw-bold">{{ t.welcome_user.replace("{name}", user.name) }}</h4>
              <div v-if="user.student?.course || user.student?.section" class="mb-3">
                <span class="badge bg-light text-dark border px-3 py-2">
                  <i class="fas fa-graduation-cap me-2 text-primary"></i>
                  {{ user.student?.course }}
                  {{ user.student?.section ? "- " + user.student?.section : "" }}
                </span>
              </div>
              <p class="text-muted mb-4">{{ t.eval_desc }}</p>
              <router-link
                to="/evaluate"
                class="btn px-4"
                :class="evaluationStatus === 'open' ? 'btn-primary' : 'btn-secondary disabled'"
                :style="evaluationStatus !== 'open' ? 'opacity: 0.6; pointer-events: none;' : ''"
              >
                <i class="fas fa-star me-2"></i>
                {{ evaluationStatus === "open" ? t.start_eval : t.eval_closed }}
              </router-link>
            </div>
          </div>

          <!-- Office Evaluation Section -->
          <div class="office-feedback-header mb-3 mx-3 mx-md-0">
            <h5 class="fw-800 mb-1">
              <i class="fas fa-building me-2 text-primary"></i>
              Office Feedback
            </h5>
            <p class="text-muted small mb-0">Evaluate campus offices to help improve their services.</p>
          </div>
          <div v-if="officesLoading" class="py-3 mx-3 mx-md-0">
            <SkeletonLoader variant="cards" :rows="3" />
          </div>
          <div v-else class="row g-3 mx-3 mx-md-0">
            <div v-for="office in offices" :key="office.id" class="col-12 col-sm-6 col-lg-4 d-flex">
              <div class="office-eval-card flex-grow-1">
                <div class="d-flex align-items-start gap-3">
                  <div class="office-icon-sm flex-shrink-0"><i class="fas fa-building"></i></div>
                  <div class="min-w-0 flex-grow-1">
                    <h6 class="fw-700 mb-1 text-truncate">{{ office.name }}</h6>
                    <p class="text-muted office-desc mb-2">{{ office.description || "No description" }}</p>
                    <div v-if="office.location" class="d-flex align-items-center gap-1 mb-3">
                      <span class="text-muted smallest">
                        <i class="fas fa-map-marker-alt me-1"></i>
                        {{ office.location }}
                      </span>
                    </div>
                    <router-link
                      :to="`/evaluate-office/${office.id}`"
                      class="btn btn-sm btn-outline-primary rounded-pill px-3"
                    >
                      <i class="fas fa-star me-1"></i>
                      Evaluate
                    </router-link>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="!offices.length" class="col-12 text-center py-4">
              <p class="text-muted">No offices available for evaluation.</p>
            </div>
          </div>
        </template>

        <template v-if="dashboardMode === 'faculty'">
          <div class="faculty-dashboard fade-in-up">
            <!-- Welcome hero -->
            <section class="faculty-hero">
              <div class="faculty-hero-main">
                <div class="faculty-hero-avatar" aria-hidden="true">{{ userInitials }}</div>
                <div class="faculty-hero-text">
                  <span class="faculty-hero-badge">Faculty</span>
                  <h2 class="faculty-hero-title">Hello, {{ userFirstName }}</h2>
                  <p class="faculty-hero-desc mb-0">
                    Review your teaching effectiveness scores and student comments in one place.
                  </p>
                </div>
              </div>
              <img
                src="/assets/img/neust_logo.webp"
                alt=""
                class="faculty-hero-logo d-none d-md-block"
                aria-hidden="true"
              />
            </section>

            <!-- Quick actions -->
            <section class="faculty-actions" aria-label="Quick actions">
              <router-link to="/reports" class="faculty-action-card faculty-action-card--primary">
                <div class="faculty-action-icon">
                  <i class="fas fa-chart-line"></i>
                </div>
                <div class="faculty-action-body">
                  <h3 class="faculty-action-title">Ratings Overview</h3>
                  <p class="faculty-action-desc">Charts and performance breakdown</p>
                </div>
                <i class="fas fa-chevron-right faculty-action-arrow"></i>
              </router-link>
              <router-link to="/set-report" class="faculty-action-card">
                <div class="faculty-action-icon faculty-action-icon--muted">
                  <i class="fas fa-file-invoice"></i>
                </div>
                <div class="faculty-action-body">
                  <h3 class="faculty-action-title">Detailed SET Report</h3>
                  <p class="faculty-action-desc">Full evaluation report document</p>
                </div>
                <i class="fas fa-chevron-right faculty-action-arrow"></i>
              </router-link>
            </section>

            <!-- Ratings overview by subject -->
            <section class="faculty-feedback-section faculty-ratings-section">
              <div class="faculty-feedback-toolbar">
                <div class="faculty-feedback-heading">
                  <i class="fas fa-chart-line"></i>
                  <div>
                    <h2 class="faculty-feedback-title">Evaluation Ratings Overview</h2>
                    <p class="faculty-feedback-subtitle mb-0">
                      Performance across all your subjects ·
                      <router-link to="/reports" class="fw-bold ms-1">View full overview</router-link>
                    </p>
                  </div>
                </div>
                <div v-if="mySubjectRatings?.overall?.average != null" class="faculty-rating-overall">
                  <span class="badge rounded-pill" :class="getRatingBadgeClass(mySubjectRatings.overall.average)">
                    Overall {{ Number(mySubjectRatings.overall.average).toFixed(2) }} ·
                    {{ getRatingLabel(mySubjectRatings.overall.average) }}
                  </span>
                </div>
              </div>

              <div v-if="mySubjectRatingsLoading" class="p-3">
                <SkeletonLoader variant="list" :rows="3" />
              </div>

              <div v-else-if="mySubjectRatings?.subjects?.length" class="faculty-feedback-list faculty-rating-list">
                <button
                  v-for="s in mySubjectRatings.subjects"
                  :key="s.subject_code"
                  type="button"
                  class="faculty-rating-row"
                  :disabled="s.evaluations <= 0"
                  :title="s.evaluations > 0 ? 'View full breakdown for this subject' : 'No evaluations yet'"
                  @click="s.evaluations > 0 && $router.push({ path: '/reports', query: { subject: s.subject_code } })"
                >
                  <div class="faculty-rating-info">
                    <span class="faculty-rating-code">{{ s.subject_code }}</span>
                    <span v-if="s.subject_name" class="faculty-rating-name">{{ s.subject_name }}</span>
                  </div>
                  <div class="faculty-rating-bar">
                    <div
                      class="faculty-rating-bar-fill"
                      :class="s.average != null ? getRatingBadgeClass(s.average) : ''"
                      :style="{ width: s.average != null ? (Number(s.average) / 5) * 100 + '%' : '0%' }"
                    ></div>
                  </div>
                  <span class="faculty-rating-value">
                    {{ s.average != null ? Number(s.average).toFixed(2) : "—" }}
                  </span>
                  <span
                    class="badge rounded-pill"
                    :class="s.average != null ? getRatingBadgeClass(s.average) : 'bg-secondary text-white'"
                  >
                    {{ s.average != null ? getRatingLabel(s.average) : "No data" }}
                  </span>
                  <span class="faculty-rating-count">{{ s.evaluations }} eval</span>
                </button>
              </div>

              <div v-else class="faculty-feedback-state">
                <i class="fas fa-chart-line fa-2x opacity-25 mb-3"></i>
                <p class="text-muted small mb-0">No subjects found for your assignments yet.</p>
              </div>
            </section>

            <!-- Student feedback -->
            <section class="faculty-feedback-section">
              <div class="faculty-feedback-toolbar">
                <div class="faculty-feedback-heading">
                  <i class="fas fa-comment-dots"></i>
                  <div>
                    <h2 class="faculty-feedback-title">Student Feedback</h2>
                    <p class="faculty-feedback-subtitle mb-0">
                      {{ myFeedbacks.length }} recent {{ myFeedbacks.length === 1 ? "comment" : "comments" }}
                      <span v-if="!myFeedbackLoading && myFeedbacks.length > 0">
                        — click a subject to view all its feedback
                      </span>
                    </p>
                  </div>
                </div>
                <div class="faculty-feedback-filters">
                  <CustomSelect
                    v-model="feedbackFilters.semester"
                    :options="semesterOptions"
                    class="faculty-filter-select"
                    @change="fetchMyFeedback"
                  />
                  <CustomSelect
                    v-model="feedbackFilters.academic_year"
                    :options="yearOptions"
                    class="faculty-filter-select"
                    @change="fetchMyFeedback"
                  />
                </div>
              </div>

              <div v-if="myFeedbackLoading" class="d-flex flex-column gap-3">
                <SkeletonLoader variant="list" :rows="3" />
              </div>

              <div v-else-if="myFeedbacks.length > 0" class="faculty-feedback-list">
                <article
                  v-for="item in myFeedbacks"
                  :key="item.id"
                  class="faculty-feedback-card"
                  :class="getRatingAccentClass(item.rating)"
                >
                  <div class="faculty-feedback-card-top">
                    <span
                      class="faculty-feedback-tag"
                      :class="{
                        'faculty-feedback-tag--clickable': user.role === 'faculty' && !!item.subject_code,
                      }"
                      :title="
                        user.role === 'faculty' && item.subject_code ? 'View all feedback for this subject' : undefined
                      "
                      @click="user.role === 'faculty' && item.subject_code && openSubjectFeedback(item.subject_code)"
                    >
                      <template v-if="user.role === 'faculty'">
                        {{ item.subject_code || "General" }}
                        <i v-if="item.subject_code" class="fas fa-chevron-right ms-1"></i>
                      </template>
                      <template v-else>{{ item.semester }} · {{ item.academic_year }}</template>
                    </span>
                    <span class="badge rounded-pill shadow-none" :class="getRatingBadgeClass(item.rating)">
                      {{ getRatingLabel(item.rating) }}
                    </span>
                  </div>
                  <blockquote class="faculty-feedback-quote">"{{ item.text }}"</blockquote>
                  <footer class="faculty-feedback-meta">
                    <span>
                      <i class="fas fa-user-shield me-1"></i>
                      Anonymous
                    </span>
                    <span>
                      <i class="fas fa-calendar-alt me-1"></i>
                      {{ new Date(item.created_at).toLocaleDateString() }}
                    </span>
                    <span>
                      <i class="fas fa-clock me-1"></i>
                      {{ new Date(item.created_at).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) }}
                    </span>
                  </footer>
                </article>
              </div>

              <div v-else class="faculty-feedback-state">
                <i class="fas fa-inbox fa-2x opacity-25 mb-3"></i>
                <p class="text-muted small mb-0">No student feedback for the selected period.</p>
              </div>
            </section>
          </div>

          <!-- All feedback for one subject (across sections / years) -->
          <div ref="subjectModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-md-down">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title fw-800 d-flex align-items-center gap-2">
                    <i class="fas fa-book-open"></i>
                    {{ activeSubjectCode }}
                  </h5>
                  <button type="button" class="btn-close" @click="showSubjectModal = false"></button>
                </div>
                <div class="modal-body">
                  <p class="small text-muted mb-3">
                    All anonymous feedback for this subject — every section, year level and period.
                    {{ subjectFeedbacks.length }}
                    {{ subjectFeedbacks.length === 1 ? "comment" : "comments" }} found.
                  </p>

                  <div v-if="subjectFeedbackLoading" class="d-flex flex-column gap-3">
                    <SkeletonLoader variant="list" :rows="3" />
                  </div>

                  <div
                    v-else-if="subjectFeedbacks.length > 0"
                    class="faculty-feedback-list faculty-feedback-list--modal"
                  >
                    <article
                      v-for="item in subjectFeedbacks"
                      :key="item.id"
                      class="faculty-feedback-card"
                      :class="getRatingAccentClass(item.rating)"
                    >
                      <div class="faculty-feedback-card-top">
                        <span class="badge rounded-pill shadow-none ms-auto" :class="getRatingBadgeClass(item.rating)">
                          {{ getRatingLabel(item.rating) }}
                        </span>
                      </div>
                      <blockquote class="faculty-feedback-quote">"{{ item.text }}"</blockquote>
                      <footer class="faculty-feedback-meta">
                        <span>
                          <i class="fas fa-user-shield me-1"></i>
                          Anonymous
                        </span>
                        <span>
                          <i class="fas fa-graduation-cap me-1"></i>
                          {{ item.semester }} · {{ item.academic_year }}
                        </span>
                        <span>
                          <i class="fas fa-calendar-alt me-1"></i>
                          {{ new Date(item.created_at).toLocaleDateString() }}
                        </span>
                        <span>
                          <i class="fas fa-clock me-1"></i>
                          {{
                            new Date(item.created_at).toLocaleTimeString([], {
                              hour: "2-digit",
                              minute: "2-digit",
                            })
                          }}
                        </span>
                      </footer>
                    </article>
                  </div>

                  <div v-else class="faculty-feedback-state">
                    <i class="fas fa-inbox fa-2x opacity-25 mb-3"></i>
                    <p class="text-muted small mb-0">No feedback for this subject yet.</p>
                  </div>
                </div>
                <div class="modal-footer">
                  <button
                    type="button"
                    class="btn btn-outline-secondary rounded-pill px-4"
                    @click="showSubjectModal = false"
                  >
                    Close
                  </button>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, inject, computed, watch } from "vue";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import CustomSelect from "../components/CustomSelect.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import Pagination from "../components/Pagination.vue";
import api from "../services/api.js";
import { useBootstrapModal } from "../composables/useBootstrapModal.js";
import { useLanguage } from "../helpers/language.js";
import { translations } from "../helpers/translations.js";
import { DEFAULT_SEMESTERS, defaultAcademicYears, asStringArray, fetchAcademicConfig } from "../helpers/academic.js";

const { currentLang } = useLanguage();
const t = computed(() => translations[currentLang.value]);

const can = inject("can");

const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});

// Role-based landing: students and faculty always get their own dashboard,
// regardless of any grants. Only other roles see the admin summary, gated
// by dashboard.view — so granting a student dashboard.view must never
// hijack them into the admin view.
const dashboardMode = computed(() => {
  if (user.value.role === "student") return "student";
  if (user.value.role === "faculty") return "faculty";
  return can("dashboard.view") ? "summary" : "none";
});

const stats = ref({
  total_faculty: 0,
  total_students: 0,
  total_evaluations: 0,
  average_rating: 0,
  performance_overview: [],
  rating_distribution: [],
  comments: [],
});
const activeTab = ref("faculty");

// Summary status: evaluation activity + online students (GET /dashboard/status)
const status = ref({
  generated_at: null,
  period: {},
  series_labels: [],
  students_finished: 0,
  students_submitted_series: [],
  evaluations_submitted: 0,
  evaluations_submitted_series: [],
  failed_logins: null,
  failed_logins_series: [],
  access_errors_visible: false,
  online_students: {
    window_minutes: 5,
    total_online: 0,
    total_students: 0,
    peak_today: 0,
    peak_date: null,
    by_department: [],
    by_year: [],
    by_department_year: [],
  },
});

// Account access log (GET /auth/login-logs, permission.manage only)
const loginLog = ref({
  summary: { success: 0, failed: 0, locked: 0, inactive: 0, total: 0 },
  data: [],
});

const onlineStudents = computed(() => status.value.online_students || {});
const onlineByDepartment = computed(() => onlineStudents.value.by_department || []);
const onlineByYear = computed(() => onlineStudents.value.by_year || []);
const accessSummary = computed(
  () => loginLog.value.summary || { success: 0, failed: 0, locked: 0, inactive: 0, total: 0 },
);
const loginRows = computed(() => loginLog.value.data || []);

// Full account access history — "View all attempts" modal
const showAccessModal = ref(false);
const { modalEl: accessModalEl } = useBootstrapModal(showAccessModal);

const accessRows = ref([]);
const accessLoading = ref(false);
const accessPerPage = ref(25);
const accessStatus = ref("");
const accessSearch = ref("");
const accessPagination = ref({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 });

const accessTabs = [
  { key: "", label: "All" },
  { key: "success", label: "Success" },
  { key: "failed", label: "Failed" },
  { key: "locked", label: "Locked" },
  { key: "inactive", label: "Inactive" },
];

const accessTabCount = (key) => {
  const summary = accessSummary.value;
  return Number(key ? summary[key] || 0 : summary.total || 0);
};

const statusPeriodLabel = computed(() => {
  const period = status.value.period || {};
  const parts = [period.semester, period.academic_year].filter(Boolean);
  return parts.length ? parts.join(" · ") : "active period";
});

const statusItems = computed(() => {
  const accessVisible = status.value.access_errors_visible;
  return [
    {
      tone: "success",
      label: "students finished evaluating",
      value: status.value.students_finished ?? 0,
      detail: "completed every evaluatee this period",
    },
    {
      tone: "danger",
      label: "accounts experiencing errors",
      value: accessVisible ? (status.value.failed_logins ?? 0) : "—",
      detail: accessVisible ? "failed logins · last 7 days" : "restricted view",
    },
    {
      tone: "primary",
      label: "evaluations submitted",
      value: status.value.evaluations_submitted ?? 0,
      detail: statusPeriodLabel.value,
    },
  ];
});

const officeStats = ref({
  total_offices: 0,
  active_offices: 0,
  total_feedback: 0,
  today_feedback: 0,
  satisfaction_rate: 0,
  monthly_stats: [],
  visitor_type_distribution: {},
});
const officeTrend = ref([]);
const officeVisitorTypes = ref({});
const visitorHeatmap = ref({});
const visitorHeatmapDays = ref(182);
const visitorTypesOrder = ["student", "parent", "faculty", "alumni", "visitor", "others"];

const offices = ref([]);
const officesLoading = ref(false);

const evaluationStatus = ref("closed");
const myFeedbacks = ref([]);
const myFeedbackLoading = ref(false);
const feedbackFilters = ref({
  semester: "all",
  academic_year: "all",
});

const showSubjectModal = ref(false);
const { modalEl: subjectModalEl } = useBootstrapModal(showSubjectModal);
const activeSubjectCode = ref("");
const subjectFeedbacks = ref([]);
const subjectFeedbackLoading = ref(false);

const mySubjectRatings = ref(null);
const mySubjectRatingsLoading = ref(false);

const semesterList = ref([...DEFAULT_SEMESTERS]);

const semesterOptions = computed(() => [
  { label: "All Semesters", value: "all" },
  ...semesterList.value.map((s) => ({ label: s, value: s })),
]);

const yearOptions = ref([{ label: "All Years", value: "all" }]);

const userFirstName = computed(() => {
  const name = user.value?.name || "";
  if (name.includes(",")) {
    const given = name.split(",")[1]?.trim() || "";
    return given.split(/\s+/)[0] || name;
  }
  return name.split(/\s+/)[0] || name;
});

const userInitials = computed(() => {
  const name = user.value?.name || "";
  const parts = name.includes(",") ? name.split(",").map((p) => p.trim()) : name.split(/\s+/);
  if (parts.length >= 2) {
    const first = parts[0];
    const last = parts[parts.length - 1];
    return `${first[0] || ""}${last[0] || ""}`.toUpperCase();
  }
  return (parts[0]?.slice(0, 2) || "U").toUpperCase();
});

let facultyChart = null;
let ratingsChart = null;
let officeTrendChart = null;
let facultyDelayed = false;
let ratingsDelayed = false;

function formatMonthLabel(month) {
  const [year, m] = String(month || "").split("-");
  if (!year || !m) return month;
  const date = new Date(Number(year), Number(m) - 1, 1);
  return date.toLocaleDateString(undefined, { month: "short" });
}

function ratingBarWidth(count) {
  const distribution = stats.value.rating_distribution || [];
  const max = Math.max(...distribution.map((n) => Number(n) || 0), 1);
  return `${Math.round(((Number(count) || 0) / max) * 100)}%`;
}

function onlineBar(online, total) {
  if (!total) return "0%";
  return `${Math.max(online > 0 ? 4 : 0, Math.round((online / total) * 100))}%`;
}

function formatLogTime(value) {
  if (!value) return "";
  try {
    return new Date(String(value).replace(" ", "T")).toLocaleString(undefined, {
      month: "short",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    });
  } catch {
    return value;
  }
}

function sparklinePath(data, w = 80, h = 32) {
  if (!data || data.length < 2) return "";
  const mx = Math.max(...data);
  const mn = Math.min(...data);
  const r = mx - mn || 1;
  return data
    .map((v, i) => {
      const x = (i / (data.length - 1)) * w;
      const y = h - ((v - mn) / r) * (h - 6) - 3;
      return `${x.toFixed(1)},${y.toFixed(1)}`;
    })
    .join(" ");
}

function sparklineFill(points, h = 32) {
  if (!points) return "";
  const coords = points.split(" ");
  const first = coords[0];
  const last = coords[coords.length - 1];
  return `M${first} L${points} L${last.split(",")[0]},${h} L${first.split(",")[0]},${h}Z`;
}

const sparkFaculty = computed(() => sparklinePath(stats.value.performance_overview?.map((p) => p.average) || []));
const sparkStudents = computed(() => sparklinePath(stats.value.rating_distribution || []));
const sparkEvals = computed(() =>
  sparklinePath((stats.value.performance_overview?.map((p) => p.average) || []).slice(0, -1)),
);
const sparkRating = computed(() => sparklinePath([...(stats.value.rating_distribution || [])].reverse()));
const sparkFacultyFill = computed(() => sparklineFill(sparkFaculty.value));
const sparkStudentsFill = computed(() => sparklineFill(sparkStudents.value));
const sparkEvalsFill = computed(() => sparklineFill(sparkEvals.value));
const sparkRatingFill = computed(() => sparklineFill(sparkRating.value));

// ── Year × Month visitor heatmap table ──
const heatmapMonthLabels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

const heatmapTableRows = computed(() => {
  const hm = visitorHeatmap.value || {};
  const monthly = {};

  for (const [dayKey, types] of Object.entries(hm)) {
    const parts = dayKey.split("-");
    if (parts.length < 3) continue;
    const year = Number(parts[0]);
    const month = Number(parts[1]) - 1;
    if (!monthly[year]) monthly[year] = {};
    if (!monthly[year][month]) monthly[year][month] = 0;
    for (const v of Object.values(types || {})) {
      monthly[year][month] += Number(v) || 0;
    }
  }

  const years = Object.keys(monthly)
    .map(Number)
    .sort((a, b) => b - a);

  let globalMax = 0;
  for (const year of years) {
    for (let m = 0; m < 12; m++) {
      const c = monthly[year]?.[m] || 0;
      if (c > globalMax) globalMax = c;
    }
  }

  return years.map((year) => ({
    year,
    months: Array.from({ length: 12 }, (_, m) => {
      const count = monthly[year]?.[m] || 0;
      const monthName = heatmapMonthLabels[m];
      let level = "heat-empty";
      if (count > 0 && globalMax > 0) {
        const r = count / globalMax;
        if (r <= 0.25) level = "heat-low";
        else if (r <= 0.5) level = "heat-mid";
        else if (r <= 0.75) level = "heat-high";
        else level = "heat-max";
      }
      return {
        count,
        level,
        tooltip: count
          ? `${count} ${count === 1 ? "visit" : "visits"} · ${monthName} ${year}`
          : `No visits · ${monthName} ${year}`,
      };
    }),
  }));
});

const heatmapTotal = computed(() => visitorTypesOrder.reduce((sum, t) => sum + visitorTypeTotal(t), 0));

const heatmapHasData = computed(() => heatmapTotal.value > 0);

function visitorTypeTotal(type) {
  return Number(officeVisitorTypes.value?.[type]) || 0;
}

const fetchDashboardStats = async (type = "faculty") => {
  try {
    const res = await api.get(`/reports/dashboard?evaluatee_type=${type}`);
    stats.value = res.data;

    await nextTick();
    initCharts();
  } catch (e) {
    console.error("Error fetching dashboard stats:", e);
  }
};

const fetchStatus = async () => {
  try {
    const res = await api.get("/dashboard/status");
    status.value = res.data;
  } catch (e) {
    console.error("Error fetching dashboard status:", e);
  }
};

// 60s poll for the live "Students Online" panel (cleared on unmount).
let statusTimer = null;

const fetchLoginLogs = async () => {
  if (!can("permission.manage")) return;
  try {
    const res = await api.get("/auth/login-logs", { params: { per_page: 8 } });
    loginLog.value = res.data;
  } catch (e) {
    console.error("Error fetching account access log:", e);
  }
};

const fetchAccessLogs = async (page = 1) => {
  accessLoading.value = true;
  try {
    const params = { page, per_page: accessPerPage.value };
    if (accessStatus.value) params.status = accessStatus.value;
    if (accessSearch.value) params.q = accessSearch.value;

    const res = await api.get("/auth/login-logs", { params });
    accessRows.value = res.data.data || [];
    accessPagination.value = {
      current_page: res.data.current_page || 1,
      last_page: res.data.last_page || 1,
      from: res.data.from || 0,
      to: res.data.to || 0,
      total: res.data.total || 0,
      per_page: res.data.per_page || accessPerPage.value,
    };
  } catch (e) {
    console.error("Error fetching account access history:", e);
    accessRows.value = [];
  } finally {
    accessLoading.value = false;
  }
};

const openAccessLog = () => {
  showAccessModal.value = true;
  fetchAccessLogs(1);
};

const setAccessStatus = (key) => {
  if (accessStatus.value === key) return;
  accessStatus.value = key;
  fetchAccessLogs(1);
};

const changeAccessPerPage = (value) => {
  accessPerPage.value = value;
  fetchAccessLogs(1);
};

let accessSearchTimer = null;
watch(accessSearch, () => {
  clearTimeout(accessSearchTimer);
  accessSearchTimer = setTimeout(() => fetchAccessLogs(1), 300);
});

const fetchOfficeStats = async () => {
  try {
    const res = await api.get(`/office-reports/dashboard`);
    officeStats.value = {
      total_offices: res.data.total_offices || 0,
      active_offices: res.data.active_offices || 0,
      total_feedback: res.data.total_feedback || 0,
      today_feedback: res.data.today_feedback || 0,
      satisfaction_rate: res.data.satisfaction_rate || 0,
      monthly_stats: res.data.monthly_stats || [],
      visitor_type_distribution: res.data.visitor_type_distribution || {},
    };
    officeTrend.value = (res.data.monthly_stats || []).map((item) => ({
      month: item.month,
      count: item.count,
      satisfaction_rate: Number(item.satisfaction_rate || 0).toFixed(2),
    }));
    officeVisitorTypes.value = res.data.visitor_type_distribution || {};
    visitorHeatmap.value = res.data.visitor_heatmap || {};
    visitorHeatmapDays.value = Number(res.data.visitor_heatmap_days) || 182;

    await nextTick();
    initCharts();
  } catch (e) {
    console.error("Error fetching office dashboard stats:", e);
  }
};

const fetchMyFeedback = async () => {
  myFeedbackLoading.value = true;
  try {
    const params = {
      semester: feedbackFilters.value.semester,
      academic_year: feedbackFilters.value.academic_year,
      latest_per_subject: 1,
    };
    const res = await api.get("/reports/my-feedback", { params });
    myFeedbacks.value = res.data.feedbacks || [];
  } catch (e) {
    console.error("Error fetching my feedback:", e);
    myFeedbacks.value = [];
  } finally {
    myFeedbackLoading.value = false;
  }
};

// Subject drill-down: fetches every comment for one subject across all
// sections, year levels and periods (backend skips semester/year filters
// when subject_code is present). Student identity is never returned.
const openSubjectFeedback = async (subjectCode) => {
  if (!subjectCode) return;
  activeSubjectCode.value = subjectCode;
  subjectFeedbacks.value = [];
  subjectFeedbackLoading.value = true;
  showSubjectModal.value = true;
  try {
    const res = await api.get("/reports/my-feedback", { params: { subject_code: subjectCode } });
    subjectFeedbacks.value = res.data.feedbacks || [];
  } catch (e) {
    console.error("Error fetching subject feedback:", e);
    subjectFeedbacks.value = [];
  } finally {
    subjectFeedbackLoading.value = false;
  }
};

const fetchMySubjectRatings = async () => {
  mySubjectRatingsLoading.value = true;
  try {
    const res = await api.get("/reports/my-subject-ratings");
    mySubjectRatings.value = res.data;
  } catch (e) {
    console.error("Error fetching subject ratings:", e);
    mySubjectRatings.value = null;
  } finally {
    mySubjectRatingsLoading.value = false;
  }
};

const setupFeedbackFilterOptions = (settings) => {
  // Year options come from System Settings so the filter only offers
  // configured academic years (plus the active year as a safety net).
  const configuredYears = asStringArray(settings.academic_year_options, defaultAcademicYears());
  if (settings.active_academic_year && !configuredYears.includes(settings.active_academic_year)) {
    configuredYears.push(settings.active_academic_year);
  }
  yearOptions.value = [{ label: "All Years", value: "all" }, ...configuredYears.map((y) => ({ label: y, value: y }))];

  if (settings.active_semester) {
    feedbackFilters.value.semester = settings.active_semester;
  }
  if (settings.active_academic_year) {
    feedbackFilters.value.academic_year = settings.active_academic_year;
  }
};

onMounted(async () => {
  fetchAcademicConfig().then((c) => {
    semesterList.value = c.semesters;
  });
  if (dashboardMode.value === "summary") {
    fetchDashboardStats(activeTab.value);
    fetchStatus();
    // Keep the "Students Online" panel live (and today's peak sampling).
    statusTimer = setInterval(fetchStatus, 60000);
    fetchLoginLogs();
    if (can("office.report.view")) {
      fetchOfficeStats();
    }
  }

  try {
    const resSettings = await api.get("/settings");
    evaluationStatus.value = resSettings.data.evaluation_status || "closed";

    if (dashboardMode.value === "faculty") {
      setupFeedbackFilterOptions(resSettings.data);
      await Promise.all([fetchMyFeedback(), fetchMySubjectRatings()]);
    }
  } catch (e) {
    console.error("Error fetching settings:", e);
  }

  // Fetch offices for students
  if (dashboardMode.value === "student") {
    officesLoading.value = true;
    try {
      const res = await api.get("/offices/all");
      offices.value = res.data;
    } catch (e) {
      console.error("Error fetching offices:", e);
    } finally {
      officesLoading.value = false;
    }
  }
});

function getRatingBadgeClass(rating) {
  if (rating >= 4.5) return "bg-success text-white";
  if (rating >= 3.5) return "bg-primary text-white";
  if (rating >= 2.5) return "bg-warning text-dark";
  if (rating >= 1.5) return "bg-orange text-white";
  return "bg-danger text-white";
}

function getRatingAccentClass(rating) {
  if (rating >= 4.5) return "faculty-feedback-card--excellent";
  if (rating >= 3.5) return "faculty-feedback-card--very-good";
  if (rating >= 2.5) return "faculty-feedback-card--good";
  if (rating >= 1.5) return "faculty-feedback-card--fair";
  return "faculty-feedback-card--poor";
}

function getRatingLabel(rating) {
  if (rating >= 4.5) return "Excellent";
  if (rating >= 3.5) return "Very Good";
  if (rating >= 2.5) return "Good";
  if (rating >= 1.5) return "Fair";
  return "Poor";
}

function destroyCharts() {
  [facultyChart, ratingsChart, officeTrendChart].forEach((chart) => {
    if (chart) {
      chart.destroy();
    }
  });

  facultyChart = null;
  ratingsChart = null;
  officeTrendChart = null;
}

onUnmounted(() => {
  destroyCharts();
  clearTimeout(accessSearchTimer);
  clearInterval(statusTimer);
});

function initCharts() {
  // Lazy-load Chart.js only when needed
  import("chart.js").then(({ Chart, registerables }) => {
    Chart.register(...registerables);

    // Reset delay flags for a fresh animation on every init
    facultyDelayed = false;
    ratingsDelayed = false;

    // Destroy existing instances before creating new ones
    destroyCharts();

    const facultyCtx = document.getElementById("facultyChart");
    if (facultyCtx && stats.value.performance_overview) {
      const labels = stats.value.performance_overview.map((item) => item.label);
      const data = stats.value.performance_overview.map((item) => item.average);

      const textPrimary = "#374151";
      const gridColor = "rgba(0, 0, 0, 0.05)";

      facultyChart = new Chart(facultyCtx, {
        type: "bar",
        data: {
          labels: labels,
          datasets: [
            {
              label: "Average Score",
              data: data,
              backgroundColor: "rgba(25,25,112,0.7)",
              borderRadius: 6,
              order: 2,
            },
            {
              label: "Trend",
              data: data,
              type: "line",
              borderColor: "#191970",
              borderWidth: 2,
              pointBackgroundColor: "#191970",
              pointBorderColor: "#fff",
              pointBorderWidth: 2,
              pointRadius: 4,
              pointHoverRadius: 6,
              tension: 0,
              fill: {
                target: "origin",
                above: "rgba(25,25,112,0.12)",
              },
              order: 1,
            },
          ],
        },
        options: {
          plugins: { legend: { display: false } },
          scales: {
            y: {
              min: 0,
              max: 5,
              ticks: { stepSize: 1, color: textPrimary },
              grid: { color: gridColor },
            },
            x: {
              ticks: { color: textPrimary },
            },
          },
          responsive: true,
          maintainAspectRatio: false,
          animations: {
            y: {
              from: (context) => (context.type === "data" ? 0 : undefined),
              duration: 1500,
              easing: "easeOutQuart",
              delay: (context) => (context.type === "data" && !facultyDelayed ? context.index * 150 : 0),
            },
            opacity: {
              from: 0,
              duration: 1500,
              delay: (context) => (context.type === "data" && !facultyDelayed ? context.index * 150 : 0),
            },
          },
          animation: {
            onComplete: () => {
              facultyDelayed = true;
            },
          },
        },
      });
    }

    const ratingCtx = document.getElementById("ratingsChart");
    if (ratingCtx && stats.value.rating_distribution) {
      const textPrimary = "#374151";
      ratingsChart = new Chart(ratingCtx, {
        type: "doughnut",
        data: {
          labels: ["Excellent (5)", "Very Good (4)", "Good (3)", "Fair (2)", "Poor (1)"],
          datasets: [
            {
              data: stats.value.rating_distribution,
              backgroundColor: [
                "#0e9f6e", // Green (Excellent)
                "#0A278A", // Navy (Very Good)
                "#FFC107", // Gold (Good)
                "#FADC67", // Lighter Gold (Fair)
                "#f05252", // Red (Poor)
              ],
            },
          ],
        },
        options: {
          cutout: "65%",
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "left",
              labels: {
                padding: 15,
                usePointStyle: true,
                pointStyle: "circle",
                font: { size: 11, weight: "600" },
                color: textPrimary,
              },
            },
          },
          animations: {
            spacing: {
              from: 30,
              duration: 1500,
              easing: "easeOutQuart",
              delay: (context) => (context.type === "data" && !ratingsDelayed ? 1500 + context.index * 200 : 0),
            },
            opacity: {
              from: 0,
              duration: 1500,
              delay: (context) => (context.type === "data" && !ratingsDelayed ? 1500 + context.index * 200 : 0),
            },
          },
          animation: {
            onComplete: () => {
              ratingsDelayed = true;
            },
          },
        },
      });
    }

    const officeTrendCtx = document.getElementById("officeTrendChart");
    if (officeTrendCtx && officeTrend.value.length) {
      const labels = officeTrend.value.map((item) => formatMonthLabel(item.month));
      const data = officeTrend.value.map((item) => Number(item.count) || 0);

      officeTrendChart = new Chart(officeTrendCtx, {
        type: "line",
        data: {
          labels,
          datasets: [
            {
              label: "Feedback Volume",
              data,
              borderColor: "#191970",
              backgroundColor: "rgba(25,25,112,0.12)",
              borderWidth: 3,
              pointRadius: 5,
              pointHoverRadius: 7,
              pointBackgroundColor: "#ffffff",
              pointBorderColor: "#191970",
              pointBorderWidth: 2,
              tension: 0.35,
              fill: false,
            },
          ],
        },
        options: {
          plugins: {
            legend: { display: false },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { color: "#374151" },
              grid: { color: "rgba(0,0,0,0.05)" },
            },
            x: {
              ticks: { color: "#374151" },
              grid: { display: false },
            },
          },
          responsive: true,
          maintainAspectRatio: false,
        },
      });
    }
  });
}
</script>

<style scoped>
.dashboard-type-toggle {
  display: flex;
  background: var(--bg-light);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  padding: 4px;
  gap: 4px;
}

.toggle-option {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: calc(var(--card-radius) - 4px);
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--text-muted);
  transition: all 0.2s ease;
  user-select: none;
}

.toggle-option input {
  display: none;
}

.toggle-option:hover {
  color: var(--text-dark);
  background: rgba(25, 25, 112, 0.05);
}

.toggle-option.active {
  background: var(--primary);
  color: #fff;
  box-shadow: 0 2px 8px rgba(25, 25, 112, 0.25);
}

@media (max-width: 575.98px) {
  .dashboard-type-toggle {
    width: 100%;
  }
  .toggle-option {
    flex: 1;
    justify-content: center;
  }
}

/* ── Faculty dashboard ───────────────── */
.faculty-dashboard {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  max-width: 1100px;
  margin: 0 auto;
}

.faculty-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
}

.faculty-hero-main {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  min-width: 0;
}

.faculty-hero-avatar {
  flex-shrink: 0;
  width: 52px;
  height: 52px;
  border-radius: 8px;
  background: var(--primary);
  color: #fff;
  font-weight: 500;
  font-size: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  letter-spacing: 0.02em;
}

.faculty-hero-badge {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--primary);
  background: rgba(25, 25, 112, 0.1);
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  margin-bottom: 0.35rem;
}

.faculty-hero-title {
  font-size: clamp(1.25rem, 4vw, 1.5rem);
  font-weight: 500;
  color: var(--text-main);
  margin: 0 0 0.35rem;
  line-height: 1.25;
}

.faculty-hero-desc {
  font-size: 0.9rem;
  color: var(--text-muted);
  line-height: 1.5;
}

.faculty-hero-logo {
  width: 64px;
  height: 64px;
  object-fit: contain;
  opacity: 0.85;
  flex-shrink: 0;
}

.faculty-actions {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
}

@media (min-width: 576px) {
  .faculty-actions {
    grid-template-columns: repeat(2, 1fr);
  }
}

.faculty-action-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.25rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  text-decoration: none;
  color: inherit;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease;
}

.faculty-action-card:hover {
  border-color: var(--primary);
  box-shadow: var(--card-shadow-hover);
  transform: translateY(-1px);
  color: inherit;
}

.faculty-action-card--primary {
  border-color: rgba(25, 25, 112, 0.25);
  background: linear-gradient(135deg, var(--bg-card) 0%, rgba(25, 25, 112, 0.04) 100%);
}

.faculty-action-icon {
  flex-shrink: 0;
  width: 44px;
  height: 44px;
  border-radius: 8px;
  background: var(--primary);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
}

.faculty-action-icon--muted {
  background: var(--bg-light);
  color: var(--primary);
  border: 1px solid var(--border-color);
}

.faculty-action-body {
  flex: 1;
  min-width: 0;
}

.faculty-action-title {
  font-size: 0.95rem;
  font-weight: 500;
  margin: 0 0 0.15rem;
  color: var(--text-main);
}

.faculty-action-desc {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin: 0;
  line-height: 1.35;
}

.faculty-action-arrow {
  flex-shrink: 0;
  color: var(--text-muted);
  font-size: 0.75rem;
  opacity: 0.6;
}

.faculty-feedback-section {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  overflow: hidden;
}

.faculty-feedback-toolbar {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-light);
}

@media (min-width: 768px) {
  .faculty-feedback-toolbar {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}

.faculty-feedback-heading {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.faculty-feedback-heading > i {
  font-size: 1.25rem;
  color: var(--primary);
  margin-top: 0.15rem;
}

.faculty-feedback-title {
  font-size: 1rem;
  font-weight: 500;
  margin: 0 0 0.15rem;
  color: var(--text-main);
}

.faculty-feedback-subtitle {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.faculty-feedback-filters {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  width: 100%;
}

@media (min-width: 576px) {
  .faculty-feedback-filters {
    flex-direction: row;
    width: auto;
  }
}

.faculty-filter-select {
  min-width: 0;
  width: 100%;
}

@media (min-width: 576px) {
  .faculty-filter-select {
    width: auto;
    min-width: 140px;
  }
}

.faculty-feedback-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  max-height: min(520px, 60vh);
  overflow-y: auto;
}

.faculty-feedback-card {
  padding: 1rem 1rem 1rem 1.15rem;
  background: var(--bg-light);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  border-left: 4px solid var(--border-color);
}

.faculty-feedback-card--excellent {
  border-left-color: var(--success);
}
.faculty-feedback-card--very-good {
  border-left-color: var(--primary);
}
.faculty-feedback-card--good {
  border-left-color: var(--warning);
}
.faculty-feedback-card--fair {
  border-left-color: #f97316;
}
.faculty-feedback-card--poor {
  border-left-color: var(--danger);
}

.faculty-feedback-card-top {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.faculty-feedback-tag {
  font-size: 0.7rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-muted);
}

.faculty-feedback-tag--clickable {
  color: var(--primary);
  cursor: pointer;
  transition: color 0.15s ease;
}

.faculty-feedback-tag--clickable:hover {
  color: var(--primary);
  text-decoration: underline;
}

.faculty-feedback-tag--clickable i {
  font-size: 0.6rem;
}

.faculty-feedback-list--modal {
  max-height: none;
  padding: 0;
  overflow: visible;
}

/* ── Evaluation Ratings Overview (by subject) ── */
.faculty-rating-overall {
  align-self: flex-start;
}

.faculty-rating-list {
  gap: 0.5rem;
}

.faculty-rating-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 0.85rem;
  width: 100%;
  padding: 0.75rem 1rem;
  background: var(--bg-light);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  cursor: pointer;
  text-align: left;
  transition:
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}

.faculty-rating-row:hover:not(:disabled) {
  border-color: var(--primary);
  box-shadow: 0 1px 6px rgba(25, 25, 112, 0.08);
}

.faculty-rating-row:disabled {
  opacity: 0.6;
  cursor: default;
}

.faculty-rating-info {
  display: flex;
  flex-direction: column;
  flex: 0 0 auto;
  width: clamp(120px, 22%, 220px);
  min-width: 0;
}

.faculty-rating-code {
  font-size: 0.78rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--text-main);
}

.faculty-rating-name {
  font-size: 0.72rem;
  color: var(--text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.faculty-rating-bar {
  flex: 1 1 160px;
  min-width: 120px;
  height: 8px;
  background: rgba(120, 120, 130, 0.18);
  border-radius: 999px;
  overflow: hidden;
}

.faculty-rating-bar-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.4s ease;
}

.faculty-rating-value {
  min-width: 40px;
  text-align: right;
  font-weight: 500;
}

.faculty-rating-count {
  min-width: 56px;
  text-align: right;
  font-size: 0.72rem;
  color: var(--text-muted);
}

.faculty-feedback-quote {
  margin: 0 0 0.75rem;
  font-size: 0.9rem;
  line-height: 1.55;
  color: var(--text-main);
  border: none;
  padding: 0;
}

.faculty-feedback-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1.25rem;
  font-size: 0.75rem;
  color: var(--text-muted);
}

.faculty-feedback-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1.5rem;
  text-align: center;
}

@media (max-width: 575.98px) {
  .faculty-hero {
    padding: 1rem;
  }

  .faculty-hero-avatar {
    width: 44px;
    height: 44px;
    font-size: 0.85rem;
  }

  .faculty-feedback-list {
    max-height: none;
    padding: 0.75rem;
  }
}

/* ── Animated gradient border for dashboard type selector ── */
@property --border-angle {
  syntax: "<angle>";
  initial-value: 0deg;
  inherits: false;
}

.dashboard-type-select {
  position: relative;
  border-radius: 50px;
  padding: 1px;
  overflow: hidden;
}

.dashboard-type-select::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 50px;
  background: conic-gradient(from var(--border-angle), #ffc107, #ff7b00, #191970, #232380, #232380, #191970, #ffc107);
  animation: spin-border 2s linear infinite;
  z-index: 0;
  mask:
    linear-gradient(#000 0 0) content-box,
    linear-gradient(#000 0 0);
  mask-composite: exclude;
  -webkit-mask:
    linear-gradient(#000 0 0) content-box,
    linear-gradient(#000 0 0);
  -webkit-mask-composite: xor;
  padding: 1px;
}

.dashboard-type-select > * {
  position: relative;
  z-index: 1;
  border-radius: 49px;
}

.dashboard-type-select :deep(.custom-select-trigger) {
  background: var(--bg-card) !important;
  border: none !important;
}

@keyframes spin-border {
  to {
    --border-angle: 360deg;
  }
}

/* ── Admin Dashboard Heading Dropdown ── */
.dashboard-heading-custom-select {
  min-width: 320px;
}

@media (max-width: 575.98px) {
  .dashboard-heading-custom-select {
    width: 100%;
    min-width: 0;
  }
}

.dashboard-heading-custom-select :deep(.custom-select-trigger) {
  font-size: 1.25rem;
  font-weight: 200;
  padding: 0.35rem 0.5rem;
  background: transparent;
  border: none !important;
  box-shadow: none !important;
  color: var(--text-main);
  transition: all 0.2s ease;
  min-height: auto;
}

.dashboard-heading-custom-select :deep(.custom-select-trigger:hover),
.dashboard-heading-custom-select :deep(.custom-select-trigger.active) {
  border: none !important;
  box-shadow: none !important;
  transform: none;
  opacity: 0.85;
}

.dashboard-heading-custom-select :deep(.selected-text) {
  letter-spacing: -0.01em;
}

.office-feedback-header {
  padding: 0 0.25rem;
}

.office-eval-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  padding: 1.1rem 1.15rem;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
  min-width: 0;
  overflow: hidden;
}

.office-eval-card:hover {
  border-color: var(--primary);
  box-shadow: 0 4px 16px rgba(25, 25, 112, 0.1);
}

.office-icon-sm {
  width: 40px;
  height: 40px;
  min-width: 40px;
  border-radius: 10px;
  background: rgba(25, 25, 112, 0.1);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}

.office-desc {
  font-size: 0.78rem;
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
}

.min-w-0 {
  min-width: 0;
}

.smallest {
  font-size: 0.65rem;
}

@media (max-width: 575.98px) {
  .office-eval-card {
    padding: 1rem;
  }

  .office-icon-sm {
    width: 36px;
    height: 36px;
    min-width: 36px;
    font-size: 0.9rem;
  }
}

/* ── Dashboard section dividers ── */
.section-divider {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  margin-top: 1rem;
  margin-bottom: 0.25rem;
}

.section-divider::after {
  content: "";
  flex: 1 1 auto;
  height: 1px;
  background: linear-gradient(to right, var(--border-color), transparent);
}

.section-divider-text h6 {
  font-weight: 500;
  font-size: 0.95rem;
  color: var(--text-main);
}

.section-divider-text small {
  font-size: 0.75rem;
  color: var(--text-muted);
}

/* ── Year × Month visitor heatmap table ── */
.visitor-heatmap-table-wrap {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.visitor-heatmap-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 3px;
  table-layout: fixed;
}

.visitor-heatmap-table th {
  font-size: 0.7rem;
  font-weight: 500;
  color: var(--text-muted);
  text-align: center;
  padding: 4px 2px;
  white-space: nowrap;
}

.visitor-heatmap-table .heat-year-header {
  text-align: left;
  width: 60px;
}

.visitor-heatmap-table td {
  text-align: center;
  padding: 0;
}

.visitor-heatmap-table .heat-year-cell {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--text-main);
  text-align: left;
  padding: 6px 8px 6px 2px;
  white-space: nowrap;
}

.visitor-heatmap-table .heat-month-cell {
  border-radius: 4px;
  padding: 6px 4px;
  min-width: 0;
  transition: transform 0.15s ease;
  cursor: default;
}

.visitor-heatmap-table .heat-month-cell:hover {
  transform: scale(1.15);
  z-index: 1;
}

.visitor-heatmap-table .heat-month-value {
  font-size: 0.65rem;
  font-weight: 500;
  line-height: 1;
  display: block;
}

.heat-month-cell.heat-empty {
  background: var(--bg-light, #f0f0f0);
}
.heat-month-cell.heat-empty .heat-month-value {
  color: var(--text-muted, #999);
}

.heat-month-cell.heat-low {
  background: #2d6a4f;
}
.heat-month-cell.heat-low .heat-month-value {
  color: #fff;
}

.heat-month-cell.heat-mid {
  background: #40916c;
}
.heat-month-cell.heat-mid .heat-month-value {
  color: #fff;
}

.heat-month-cell.heat-high {
  background: #52b788;
}
.heat-month-cell.heat-high .heat-month-value {
  color: #fff;
}

.heat-month-cell.heat-max {
  background: #95d5b2;
}
.heat-month-cell.heat-max .heat-month-value {
  color: #1b4332;
}

/* ── Summary status layout ─────────────────────────── */
.summary-hero .card-body {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.7rem;
  padding: 1.1rem 1.25rem;
  height: 100%;
}

.summary-hero-head {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 10px;
}

.hero-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
  background: var(--bg-light);
  color: var(--text-muted);
  flex-shrink: 0;
}

.hero-eyebrow {
  font-size: 0.68rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-muted);
}

.hero-sub {
  font-size: 0.7rem;
  color: var(--text-muted);
  opacity: 0.8;
}

.summary-hero-value {
  font-size: 2.5rem;
  font-weight: 500;
  line-height: 1;
  color: var(--text-main);
  letter-spacing: -0.03em;
}

.summary-hero-scale {
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--text-muted);
}

.rating-mini {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.rating-mini-row {
  --tone: var(--text-muted);
  display: grid;
  grid-template-columns: 32px 1fr 40px;
  gap: 8px;
  align-items: center;
  font-size: 0.7rem;
  color: var(--text-muted);
}

.rating-mini-star {
  display: flex;
  align-items: center;
  gap: 4px;
  font-weight: 500;
  color: var(--tone);
}

.rating-mini-star i {
  font-size: 0.58rem;
}

.rating-mini-track {
  height: 6px;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  border-radius: 999px;
  overflow: hidden;
}

.rating-mini-fill {
  height: 100%;
  background: var(--tone);
  border-radius: 999px;
  transition: width 0.6s ease;
}

.rating-mini-count {
  text-align: right;
  font-weight: 500;
  color: var(--text-main);
  font-variant-numeric: tabular-nums;
}

.summary-mini {
  flex-direction: row;
  align-items: center;
  gap: 12px;
  padding: 0.8rem 1.1rem;
  transition:
    border-color 0.2s ease,
    transform 0.2s ease;
}

.summary-mini:hover {
  border-color: rgba(25, 25, 112, 0.35);
  transform: translateY(-2px);
}

.mini-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  flex-shrink: 0;
  background: var(--bg-light);
  color: var(--text-muted);
}

.mini-body {
  min-width: 0;
}

.mini-label {
  font-size: 0.66rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--text-muted);
  margin-bottom: 1px;
}

.mini-value {
  font-size: 1.55rem;
  font-weight: 500;
  line-height: 1.05;
  color: var(--text-main);
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
}

.summary-status .card-header {
  padding: 12px 20px;
  font-size: 0.95rem;
}

.summary-status .status-body {
  display: flex;
  flex-direction: column;
  justify-content: center;
  min-height: 0;
  padding-top: 0.35rem;
  padding-bottom: 0.55rem;
}

.live-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.72rem;
  font-weight: 500;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--badge-success-bg);
  color: var(--badge-success-text);
}

.live-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
  animation: livePulse 1.6s ease-in-out infinite;
}

@keyframes livePulse {
  0%,
  100% {
    opacity: 1;
    transform: scale(1);
  }

  50% {
    opacity: 0.4;
    transform: scale(0.7);
  }
}

.status-item {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 12px;
  padding: 0.6rem 0;
}

.status-item.has-divider {
  border-top: 1px dashed var(--border-light);
}

.status-head {
  display: flex;
  align-items: baseline;
  gap: 8px;
  flex-wrap: wrap;
}

.status-text {
  min-width: 0;
}

.status-value {
  font-size: 1.3rem;
  font-weight: 500;
  line-height: 1.1;
  color: var(--text-main);
  font-variant-numeric: tabular-nums;
}

.status-label {
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text-main);
}

.status-detail {
  font-size: 0.72rem;
  color: var(--text-muted);
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Online students / access log ──────────────────── */
.online-hero {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 14px;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  background: var(--bg-light);
}

.online-hero-value {
  font-size: 2rem;
  font-weight: 500;
  line-height: 1;
  color: var(--success);
  font-variant-numeric: tabular-nums;
}

.online-hero-text b {
  display: block;
  font-size: 0.85rem;
  color: var(--text-main);
}

.online-hero-text span {
  font-size: 0.74rem;
  color: var(--text-muted);
}

.breakdown-heading {
  font-size: 0.72rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted);
  margin-bottom: 0.5rem;
}

.breakdown-top {
  display: flex;
  justify-content: space-between;
  font-size: 0.8rem;
  margin-bottom: 6px;
}

.breakdown-count {
  color: var(--text-muted);
  font-variant-numeric: tabular-nums;
}

.breakdown-track {
  height: 6px;
  background: var(--bg-light);
  border-radius: 999px;
  overflow: hidden;
}

.breakdown-fill {
  height: 100%;
  background: var(--primary);
  border-radius: 999px;
  transition: width 0.6s ease;
}

.dept-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.dept-item {
  padding: 8px 10px;
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  background: var(--bg-card);
}

.dept-name {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-weight: 500;
  color: var(--text-main);
}

.dept-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--border-color);
  flex-shrink: 0;
}

.dept-dot.on {
  background: var(--success);
  box-shadow: 0 0 0 3px rgba(14, 159, 110, 0.15);
}

.year-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.year-tile {
  padding: 8px 10px;
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  background: var(--bg-card);
}

.year-tile.active {
  border-color: rgba(14, 159, 110, 0.35);
  background: var(--badge-success-bg);
}

.year-name {
  font-size: 0.68rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-muted);
}

.year-count {
  font-size: 1.3rem;
  font-weight: 500;
  line-height: 1.15;
  color: var(--text-main);
  font-variant-numeric: tabular-nums;
}

.year-count span {
  font-size: 0.72rem;
  font-weight: 500;
  color: var(--text-muted);
}

.year-meta {
  font-size: 0.66rem;
  color: var(--text-muted);
}

.year-tile.active .year-count {
  color: var(--badge-success-text);
}

.year-tile.active .year-count span,
.year-tile.active .year-meta {
  color: var(--badge-success-text);
  opacity: 0.85;
}

.access-summary {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}

.access-tile {
  --tone: var(--border-color);
  padding: 10px 6px;
  border: 1px solid var(--border-light);
  border-top: 3px solid var(--tone);
  border-radius: var(--radius-md);
  background: var(--bg-card);
  text-align: center;
}

.access-tile.ok {
  --tone: var(--success);
}

.access-tile.bad {
  --tone: var(--danger);
}

.access-tile.warn {
  --tone: var(--warning);
}

.access-tile.muted {
  --tone: var(--text-muted);
}

.access-num {
  font-size: 1.3rem;
  font-weight: 500;
  line-height: 1.15;
  color: var(--text-main);
  font-variant-numeric: tabular-nums;
}

.access-lab {
  font-size: 0.66rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-muted);
}

.access-list {
  display: flex;
  flex-direction: column;
}

.access-row {
  display: grid;
  grid-template-columns: 78px 1fr auto;
  gap: 8px;
  align-items: center;
  font-size: 0.78rem;
  padding: 7px 4px;
  margin: 0 -4px;
  border-bottom: 1px dashed var(--border-light);
  border-radius: 6px;
  transition: background 0.15s ease;
}

.access-row:hover {
  background: var(--bg-light);
}

.access-row:last-child {
  border-bottom: none;
}

.access-status {
  font-size: 0.66rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 2px 6px;
  border-radius: 999px;
  text-align: center;
  background: var(--bg-light);
  color: var(--text-muted);
}

.access-status.is-success {
  background: var(--badge-success-bg);
  color: var(--badge-success-text);
}

.access-status.is-failed {
  background: var(--badge-danger-bg);
  color: var(--badge-danger-text);
}

.access-status.is-locked {
  background: var(--badge-warning-bg);
  color: var(--badge-warning-text);
}

.access-status.is-inactive {
  background: var(--badge-info-bg);
  color: var(--badge-info-text);
}

.access-id {
  color: var(--text-main);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.access-time {
  color: var(--text-muted);
  font-size: 0.72rem;
  white-space: nowrap;
}

.access-footer {
  display: flex;
  justify-content: center;
  padding-top: 10px;
  margin-top: 4px;
  border-top: 1px dashed var(--border-light);
}

.access-viewall {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-main);
  font-size: 0.75rem;
  font-weight: 500;
  padding: 6px 12px;
  border-radius: 999px;
  transition: all 0.2s ease;
}

.access-viewall:hover {
  border-color: var(--primary);
  color: var(--primary);
  background: var(--bg-light);
}

.access-viewall-count {
  font-size: 0.68rem;
  font-weight: 500;
  padding: 1px 7px;
  border-radius: 999px;
  background: var(--bg-light);
  color: var(--text-muted);
}

.access-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.access-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.access-tab {
  --tone: var(--border-color);
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid var(--border-light);
  border-top: 2px solid var(--tone);
  background: var(--bg-card);
  color: var(--text-muted);
  font-size: 0.7rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 5px 9px;
  border-radius: var(--radius-md);
  transition: all 0.18s ease;
}

.access-tab:hover {
  color: var(--text-main);
  border-color: var(--border-color);
}

.access-tab.active {
  background: var(--bg-light);
  color: var(--text-main);
  box-shadow: 0 0 0 1px var(--tone) inset;
}

.access-tab.is-success {
  --tone: var(--success);
}

.access-tab.is-failed {
  --tone: var(--danger);
}

.access-tab.is-locked {
  --tone: var(--warning);
}

.access-tab.is-inactive {
  --tone: var(--primary);
}

.access-tab-count {
  font-size: 0.68rem;
  font-variant-numeric: tabular-nums;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--bg-light);
  color: var(--text-muted);
}

.access-tab.active .access-tab-count {
  background: var(--bg-card);
  color: var(--text-main);
}

.access-search {
  width: 230px;
  max-width: 100%;
}

.access-main {
  display: flex;
  flex-direction: column;
  min-width: 0;
  gap: 1px;
}

.access-meta {
  color: var(--text-muted);
  font-size: 0.7rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.access-pager {
  margin: 0 -1rem -1rem;
}

@media (max-width: 767.98px) {
  .access-summary {
    grid-template-columns: repeat(2, 1fr);
  }

  .access-toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .access-search {
    width: 100%;
  }

  .access-row {
    grid-template-columns: 78px 1fr;
    row-gap: 2px;
  }

  .access-row .access-time {
    grid-column: 2;
  }
}

/* ── Dark theme overrides ──────────────────────────── */
[data-theme="dark"] .summary-mini:hover {
  border-color: rgba(88, 166, 255, 0.4);
}
[data-theme="dark"] .online-hero-value {
  color: var(--badge-success-text);
}

[data-theme="dark"] .dept-dot.on {
  background: #3fb950;
  box-shadow: 0 0 0 3px rgba(63, 185, 80, 0.18);
}

[data-theme="dark"] .year-tile.active {
  border-color: rgba(63, 185, 80, 0.4);
}

[data-theme="dark"] .breakdown-fill {
  background: #79c0ff;
}
</style>
