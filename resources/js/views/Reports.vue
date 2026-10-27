<template>
  <div class="d-flex">
    <Sidebar class="no-print" />
    <div class="main-wrapper w-100">
      <Navbar class="no-print"><template #title>Evaluation Ratings Overview</template></Navbar>

      <div class="content-area reports-content-area">
        <!-- Admin Faculty Selector -->
        <div
          class="card shadow-none mb-4 no-print mx-3 mx-md-0 reports-filter-card"
          v-if="$can('report.view.all') && user.role !== 'faculty'"
          style="position: relative; z-index: 900; overflow: visible !important"
        >
          <div class="card-body reports-filter-body no-print" style="overflow: visible !important">
            <!-- Small-screen filter toggle -->
            <button
              type="button"
              class="filter-toggle-btn d-lg-none"
              :aria-expanded="showFilters"
              aria-controls="reports-filters"
              @click="showFilters = !showFilters"
            >
              <i class="fas fa-filter"></i>
              <span>Filters</span>
              <i class="fas ms-auto" :class="showFilters ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
            </button>

            <div id="reports-filters" class="reports-filters" :class="{ 'is-open': showFilters }">
              <!-- Search Filter -->
              <div class="search-pill-container filter-slot--search">
                <i class="fas fa-search search-icon"></i>
                <input 
                  type="text" 
                  v-model="searchQuery" 
                  class="search-input-field" 
                  placeholder="Search faculty..."
                />
              </div>

              <!-- Dept Filter (faculty only) -->
              <div v-if="evaluateeType === 'faculty'" class="filter-slot filter-slot--dept">
                <CustomSelect
                  v-model="selectedDepartment"
                  :options="departmentOptions"
                  placeholder="All Departments"
                  @change="handleDepartmentChange"
                />
              </div>

              <!-- Main Faculty Selector -->
              <div class="filter-slot filter-slot--faculty">
                <CustomSelect
                  v-model="selectedFacultyId"
                  :options="facultyOptions"
                  placeholder="Select Faculty:"
                  @change="handleFacultyChange"
                />
              </div>

              <!-- Refresh/Reset Button -->
              <button class="refresh-pill-btn" @click="resetFilters" title="Reset Filters">
                <i class="fas fa-undo" :class="{ 'fa-spin': loading }"></i>
              </button>

              <!-- Legacy history lives on the dedicated archive page -->
              <router-link to="/archive" class="small fw-bold ms-auto" title="Review past semesters and academic years">
                <i class="fas fa-box-archive me-1"></i>
                View archive
              </router-link>
            </div>
          </div>
        </div>

        <!-- Results Section -->
        <div v-if="loading" class="py-4">
          <SkeletonLoader variant="cards" :rows="3" />
        </div>

        <div v-else-if="results" class="reports-results">
          <!-- Top Highlights (F-Pattern Header) -->
          <div class="row g-3 mb-4 reports-summary-row">
            <div class="col-12 col-md-4">
              <div class="stat-card shadow-none p-3 p-md-4 h-100 flex-column text-center">
                <div class="stat-label mb-2">Final Evaluation Score</div>
                <div class="stat-value text-success mb-2 report-final-score">
                  {{ results.final_score }}
                </div>
                <span class="badge px-3 px-md-4 py-2 rounded-pill" :class="badgeClass(results.interpretation)">
                  {{ results.interpretation }}
                </span>
              </div>
            </div>
            <div class="col-12 col-md-8">
              <div class="card shadow-none h-100">
                <div class="card-header border-0 py-3">
                  <h6 class="mb-0 fw-bold">Score Visualization</h6>
                </div>
                <div class="card-body py-2 report-chart-body">
                  <canvas id="reportChart"></canvas>
                </div>
              </div>
            </div>
          </div>

          <!-- Performance by Subject (overall or individual) -->
          <div v-if="subjectRatings" class="card shadow-none mb-4 subject-overview-card">
            <div
              class="card-header border-0 py-3 d-flex flex-wrap align-items-center justify-content-between gap-2"
            >
              <h6 class="mb-0 fw-bold">
                <i class="fas fa-book-open me-2 text-primary"></i>Performance by Subject
              </h6>
              <div class="subject-select-slot">
                <CustomSelect
                  v-model="selectedSubject"
                  :options="subjectSelectOptions"
                  @change="loadResults"
                />
              </div>
            </div>
          </div>

          <div class="reports-question-filter">
            <label for="question-rating-filter" class="form-label mb-0">Show questions with rating</label>
            <select id="question-rating-filter" v-model="selectedQuestionRating" class="form-select">
              <option value="">All ratings</option>
              <option v-for="rating in [5, 4, 3, 2, 1]" :key="rating" :value="String(rating)">
                Rating {{ rating }}
              </option>
            </select>
          </div>

          <!-- Frequency Grid — mobile cards -->
          <div class="d-md-none reports-mobile-categories mb-4">
            <div
              v-for="cat in results?.category_results"
              :key="cat?.category_name + '-mobile'"
              class="reports-category-card"
            >
              <div class="reports-category-card-head">
                <div>
                  <div class="fw-bold">{{ cat.category_name }}</div>
                  <div class="small text-muted">Weight: {{ (cat.weight * 100).toFixed(0) }}%</div>
                  <ol v-if="visibleQuestions(cat.questions).length" class="reports-question-list">
                    <li
                      v-for="question in visibleQuestions(cat.questions)"
                      :key="question.question_text"
                      class="reports-question-item"
                    >
                      <span>{{ question.question_text }}</span>
                      <span v-if="question.average_rating != null" class="reports-question-results">
                        <span class="reports-question-rating">
                          {{ Number(question.average_rating).toFixed(2) }}/5 avg ·
                          {{ question.evaluations }}
                          {{ question.evaluations === 1 ? "response" : "responses" }}
                        </span>
                        <span class="reports-question-rating-counts" aria-label="Rating distribution">
                          <span v-for="rating in [5, 4, 3, 2, 1]" :key="rating">
                            {{ rating }}: {{ question["count_" + rating] }}
                          </span>
                        </span>
                      </span>
                      <span v-else class="reports-question-rating text-muted">No ratings</span>
                    </li>
                  </ol>
                  <p
                    v-else-if="selectedQuestionRating"
                    class="reports-question-empty"
                  >
                    No questions received rating {{ selectedQuestionRating }}.
                  </p>
                </div>
                <div class="text-end">
                  <div class="fw-bold text-primary">{{ Number(cat.average_rating).toFixed(2) }}</div>
                  <div class="x-small text-muted">Avg</div>
                </div>
              </div>
              <div class="reports-freq-grid">
                <div class="reports-freq-cell">
                  <span class="reports-freq-val text-success">{{ cat.count_5 }}</span>
                  <span class="reports-freq-lbl">5</span>
                </div>
                <div class="reports-freq-cell">
                  <span class="reports-freq-val text-info">{{ cat.count_4 }}</span>
                  <span class="reports-freq-lbl">4</span>
                </div>
                <div class="reports-freq-cell">
                  <span class="reports-freq-val">{{ cat.count_3 }}</span>
                  <span class="reports-freq-lbl">3</span>
                </div>
                <div class="reports-freq-cell">
                  <span class="reports-freq-val text-warning">{{ cat.count_2 }}</span>
                  <span class="reports-freq-lbl">2</span>
                </div>
                <div class="reports-freq-cell">
                  <span class="reports-freq-val text-danger">{{ cat.count_1 }}</span>
                  <span class="reports-freq-lbl">1</span>
                </div>
              </div>
              <div class="reports-category-card-foot">
                <span class="small text-muted">Weighted score</span>
                <span class="fw-bold">{{ (cat.average_rating * cat.weight).toFixed(3) }}</span>
              </div>
            </div>
          </div>

          <!-- Frequency Grid (desktop table) -->
          <div class="card shadow-none mb-4 overflow-hidden reports-table-card d-none d-md-block">
            <div class="card-body p-0">
              <div class="reports-table-scroll" @scroll="onTableScroll">
                <table class="table table-borderless table-hover mb-0 align-middle text-center">
                  <thead :class="{ 'glass-header': tableScrolled }" class="bg-light small">
                    <tr class="text-uppercase text-muted">
                      <th rowspan="2" class="py-3 text-start ps-4" style="min-width: 250px">Category</th>
                      <th colspan="5" class="py-2 border-0">Frequency Distribution</th>
                      <th rowspan="2" class="py-3">
                        Weighted
                        <br />
                        Score
                      </th>
                      <th rowspan="2" class="py-3 pe-4">Total Avg</th>
                    </tr>
                    <tr class="text-muted small">
                      <th style="width: 60px">5</th>
                      <th style="width: 60px">4</th>
                      <th style="width: 60px">3</th>
                      <th style="width: 60px">2</th>
                      <th style="width: 60px">1</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="cat in results?.category_results" :key="cat?.category_name">
                      <td class="text-start ps-4">
                        <div class="fw-bold">
                          {{ cat.category_name }}
                        </div>
                        <div class="small text-muted">
                          Weight:
                          {{ (cat.weight * 100).toFixed(0) }}%
                        </div>
                        <ol v-if="visibleQuestions(cat.questions).length" class="reports-question-list">
                          <li
                            v-for="question in visibleQuestions(cat.questions)"
                            :key="question.question_text"
                            class="reports-question-item"
                          >
                            <span>{{ question.question_text }}</span>
                            <span v-if="question.average_rating != null" class="reports-question-results">
                              <span class="reports-question-rating">
                                {{ Number(question.average_rating).toFixed(2) }}/5 avg ·
                                {{ question.evaluations }}
                                {{ question.evaluations === 1 ? "response" : "responses" }}
                              </span>
                              <span class="reports-question-rating-counts" aria-label="Rating distribution">
                                <span v-for="rating in [5, 4, 3, 2, 1]" :key="rating">
                                  {{ rating }}: {{ question["count_" + rating] }}
                                </span>
                              </span>
                            </span>
                            <span v-else class="reports-question-rating text-muted">No ratings</span>
                          </li>
                        </ol>
                        <p
                          v-else-if="selectedQuestionRating"
                          class="reports-question-empty"
                        >
                          No questions received rating {{ selectedQuestionRating }}.
                        </p>
                      </td>
                      <td class="fw-bold text-success">
                        {{ cat.count_5 }}
                      </td>
                      <td class="text-info">
                        {{ cat.count_4 }}
                      </td>
                      <td class="text-muted">
                        {{ cat.count_3 }}
                      </td>
                      <td class="text-warning">
                        {{ cat.count_2 }}
                      </td>
                      <td class="text-danger">
                        {{ cat.count_1 }}
                      </td>
                      <td class="fw-bold">
                        {{ (cat.average_rating * cat.weight).toFixed(3) }}
                      </td>
                      <td class="pe-4">
                        <div class="d-flex align-items-center justify-content-center gap-2">
                          <span class="fw-bold text-primary">{{ Number(cat.average_rating).toFixed(2) }}</span>
                          <div class="progress" style="width: 40px; height: 6px">
                            <div
                              class="progress-bar bg-primary"
                              :style="{
                                width: (cat.average_rating / 5) * 100 + '%',
                              }"
                            ></div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- AI Insights Button (FAB) — admins only, not faculty dashboards -->
          <div v-if="canUseAiInsights" v-show="!showAiOverlay" class="ai-fab-container no-print">
            <button
              class="ai-fab"
              :class="{ 'has-insights': aiInsights, loading: loadingAi }"
              @click="toggleAiOverlay"
              title="View AI Insights"
            >
              <i v-if="loadingAi" class="fas fa-spinner fa-spin"></i>
              <AiSparkleIcon v-else :size="30" variant="gradient" custom-class="fab-sparkle" />
              <span v-if="!aiInsights && !loadingAi" class="ai-badge">Get AI Insights</span>
            </button>
          </div>

          <!-- AI Insights Modal (Bootstrap, no morph animation) -->
          <div v-if="canUseAiInsights" ref="aiModalEl" class="modal fade no-print" tabindex="-1" aria-hidden="true">
            <div
              class="modal-dialog modal-dialog-centered modal-dialog-scrollable"
              :class="isAiFullScreen ? 'modal-fullscreen' : 'modal-xl'"
            >
              <div class="modal-content ai-modal">
                <div class="modal-header ai-modal-header">
                  <div class="d-flex align-items-center gap-3">
                    <div class="ai-orb">
                      <AiSparkleIcon :size="22" variant="white" />
                    </div>
                    <div>
                      <h5 class="modal-title ai-modal-title">AI Feedback Analysis & Suggestions</h5>
                      <p class="ai-modal-sub">Gemini-powered insights from qualitative evaluations</p>
                    </div>
                  </div>
                  <div class="d-flex align-items-center gap-2 ms-auto">
                    <button
                      v-if="aiInsights && !loadingAi"
                      class="btn btn-sm ai-btn-grad"
                      @click="loadAiInsights(true)"
                    >
                      <i class="fas fa-sync-alt me-1"></i>
                      Refresh
                    </button>
                    <button
                      class="btn btn-sm ai-btn-ghost"
                      @click="toggleAiFullScreen"
                      :title="isAiFullScreen ? 'Exit fullscreen' : 'Fullscreen'"
                    >
                      <i class="fas" :class="isAiFullScreen ? 'fa-compress' : 'fa-expand'"></i>
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                </div>

                <div class="modal-body ai-modal-body">
                    <div v-if="loadingAi" class="ai-state">
                      <div class="ai-orb ai-orb--lg ai-orb--pulse">
                        <AiSparkleIcon :size="30" variant="white" />
                      </div>
                      <h5 class="ai-state__title">Generating Analytics...</h5>
                      <p class="ai-state__text">
                        Analyzing metrics and qualitative feedback for your smart dashboard.
                      </p>
                      <SkeletonLoader variant="list" :rows="4" />
                    </div>

                    <div v-else-if="aiError" class="ai-state">
                      <div class="ai-state__icon ai-state__icon--danger">
                        <i class="fas fa-exclamation-triangle"></i>
                      </div>
                      <h5 class="ai-state__title">{{ aiError }}</h5>
                      <p class="ai-state__text">
                        The AI service is currently unavailable or has reached its request limit. Please try again in a
                        few minutes.
                      </p>
                      <button class="btn ai-btn-grad ai-btn-grad--lg" @click="loadAiInsights">
                        <i class="fas fa-sync-alt me-2"></i>
                        Try Again
                      </button>
                    </div>

                    <div v-else-if="aiInsights" class="dashboard-grid">
                      <!-- 1. Top Summary Cards -->
                      <div class="row g-3 mb-4">
                        <div class="col-md-3">
                          <div class="ai-kpi ai-kpi--violet h-100">
                            <div class="ai-kpi__icon"><i class="fas fa-star"></i></div>
                            <div class="ai-kpi__body">
                              <div class="ai-kpi__label">Avg Rating</div>
                              <div class="ai-kpi__value">{{ aiInsights.metrics?.average_rating || "0.00" }}</div>
                              <div class="ai-kpi__hint">out of 5.00 overall</div>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="ai-kpi ai-kpi--blue h-100">
                            <div class="ai-kpi__icon"><i class="fas fa-users"></i></div>
                            <div class="ai-kpi__body">
                              <div class="ai-kpi__label">Responses</div>
                              <div class="ai-kpi__value">{{ aiInsights.metrics?.response_count || 0 }}</div>
                              <div class="ai-kpi__hint">evaluations analyzed</div>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="ai-kpi ai-kpi--emerald h-100">
                            <div class="ai-kpi__icon"><i class="fas fa-smile"></i></div>
                            <div class="ai-kpi__body">
                              <div class="ai-kpi__label">Positive</div>
                              <div class="ai-kpi__value ai-kpi__value--emerald">
                                {{ aiInsights.sentiment?.positive }}%
                              </div>
                              <div class="ai-kpi__hint">of sentiment mentions</div>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="ai-kpi ai-kpi--amber h-100">
                            <div class="ai-kpi__icon"><i class="fas fa-chart-line"></i></div>
                            <div class="ai-kpi__body">
                              <div class="ai-kpi__label">Trend</div>
                              <div class="ai-kpi__value" :class="trendClass">{{ trendValue }}</div>
                              <div class="ai-kpi__hint">vs previous rating</div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- 2. Charts Section -->
                      <div class="row g-4 mb-4">
                        <div class="col-md-8">
                          <div class="ai-surface h-100">
                            <div class="ai-surface__head">
                              <span class="ai-surface__icon ai-chip--violet"><i class="fas fa-chart-bar"></i></span>
                              <div>
                                <h6 class="ai-surface__title">Criteria Breakdown</h6>
                                <p class="ai-surface__sub">Average rating per criteria · scale of 0–5</p>
                              </div>
                            </div>
                            <div class="ai-surface__body">
                              <div class="ai-chart-box">
                                <canvas id="dashboardBarChart"></canvas>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="ai-surface h-100">
                            <div class="ai-surface__head">
                              <span class="ai-surface__icon ai-chip--emerald"><i class="fas fa-smile"></i></span>
                              <div>
                                <h6 class="ai-surface__title">Sentiment Mix</h6>
                                <p class="ai-surface__sub">Tone of qualitative comments</p>
                              </div>
                            </div>
                            <div class="ai-surface__body ai-surface__body--center">
                              <div v-if="sentimentTotal > 0" class="ai-chart-box">
                                <canvas id="dashboardPieChart"></canvas>
                              </div>
                              <div v-else class="ai-viz-empty">
                                <i class="fas fa-chart-pie"></i>
                                <span>No sentiment data yet</span>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- 3. Tables Section -->
                      <div class="row g-4 mb-4">
                        <div class="col-md-6">
                          <div class="ai-surface h-100">
                            <div class="ai-surface__head">
                              <span class="ai-surface__icon ai-chip--blue"><i class="fas fa-table"></i></span>
                              <div>
                                <h6 class="ai-surface__title">Criteria Performance</h6>
                                <p class="ai-surface__sub">Rating and status per criteria</p>
                              </div>
                            </div>
                            <div class="ai-surface__body ai-surface__body--flush">
                              <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 ai-table">
                                  <thead>
                                    <tr>
                                      <th class="ps-4">Criteria</th>
                                      <th>Rating</th>
                                      <th class="pe-4 text-end">Status</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <tr v-for="cat in results?.category_results" :key="cat.category_name">
                                      <td class="ps-4 fw-600 small">{{ cat.category_name }}</td>
                                      <td>
                                        <div class="ai-rating">
                                          <span class="ai-rating__val">{{ Number(cat.average_rating).toFixed(2) }}</span>
                                          <span class="ai-rating__track">
                                            <span
                                              class="ai-rating__fill"
                                              :class="getAiBarClass(cat.average_rating)"
                                              :style="{ width: (cat.average_rating / 5) * 100 + '%' }"
                                            ></span>
                                          </span>
                                        </div>
                                      </td>
                                      <td class="pe-4 text-end">
                                        <span class="ai-tag" :class="getAiTagClass(cat.average_rating)">
                                          {{ getRatingStatus(cat.average_rating) }}
                                        </span>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="ai-surface h-100">
                            <div class="ai-surface__head">
                              <span class="ai-surface__icon ai-chip--violet"><i class="fas fa-brain"></i></span>
                              <div>
                                <h6 class="ai-surface__title">AI Metric Insights</h6>
                                <p class="ai-surface__sub">What the model observed per metric</p>
                              </div>
                            </div>
                            <div class="ai-surface__body ai-surface__body--flush">
                              <div class="table-responsive">
                                <table class="table align-middle mb-0 ai-table">
                                  <thead>
                                    <tr>
                                      <th class="ps-4">Metric</th>
                                      <th>Value</th>
                                      <th class="pe-4">AI Insight</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <tr v-for="mi in aiInsights.metric_insights" :key="mi.metric">
                                      <td class="ps-4 small">
                                        <span class="ai-metric-name">
                                          <span class="ai-metric-name__dot"></span>
                                          {{ mi.metric }}
                                        </span>
                                      </td>
                                      <td>
                                        <span class="ai-chip-plain"><i class="fas fa-check me-1"></i>Analyzed</span>
                                      </td>
                                      <td class="pe-4 x-small text-muted">{{ mi.insight }}</td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- 4. AI Insights Panel -->
                      <div class="row g-4">
                        <div class="col-md-4">
                          <div class="ai-note ai-note--emerald h-100">
                            <div class="ai-note__head">
                              <span class="ai-note__icon"><i class="fas fa-check"></i></span>
                              <h6 class="ai-note__title">Key Strengths</h6>
                            </div>
                            <ul v-if="aiInsights.strengths?.length" class="ai-note__list">
                              <li v-for="s in aiInsights.strengths" :key="s">
                                <span class="ai-note__bullet"></span>
                                <span>{{ s }}</span>
                              </li>
                            </ul>
                            <p v-else class="ai-note__empty">No standout strengths in this period.</p>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="ai-note ai-note--rose h-100">
                            <div class="ai-note__head">
                              <span class="ai-note__icon"><i class="fas fa-exclamation"></i></span>
                              <h6 class="ai-note__title">Areas for Improvement</h6>
                            </div>
                            <ul v-if="aiInsights.issues?.length" class="ai-note__list">
                              <li v-for="i in aiInsights.issues" :key="i">
                                <span class="ai-note__bullet"></span>
                                <span>{{ i }}</span>
                              </li>
                            </ul>
                            <p v-else class="ai-note__empty">No recurring issues flagged by the model.</p>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="ai-note ai-note--violet h-100">
                            <div class="ai-note__head">
                              <span class="ai-note__icon"><i class="fas fa-lightbulb"></i></span>
                              <h6 class="ai-note__title">AI Recommendations</h6>
                            </div>
                            <ul v-if="aiInsights.recommendations?.length" class="ai-note__list">
                              <li v-for="r in aiInsights.recommendations" :key="r">
                                <span class="ai-note__bullet"></span>
                                <span>{{ r }}</span>
                              </li>
                            </ul>
                            <p v-else class="ai-note__empty">No recommendations generated yet.</p>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div v-else class="ai-state">
                      <div class="ai-orb ai-orb--xl">
                        <AiSparkleIcon :size="44" variant="white" />
                      </div>
                      <h3 class="ai-state__title ai-state__title--lg">Analyze Feedback with AI</h3>
                      <p class="ai-state__text ai-state__text--wide">
                        Let our AI process all qualitative comments to identify performance patterns and professional
                        growth opportunities. This takes only a few seconds.
                      </p>
                      <button class="btn ai-btn-grad ai-btn-grad--lg" @click="loadAiInsights">
                        <AiSparkleIcon :size="18" variant="white" />
                        Start Analyzing
                      </button>
                    </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-else-if="!loading && selectedFacultyId === '' && $can('report.view.all') && user.role !== 'faculty'"
          class="card border-0 shadow-sm"
        >
          <div class="card-body text-center py-5 text-muted">
            <div class="mb-3">
              <i class="fas fa-chart-line fa-4x opacity-25"></i>
            </div>
            <h5 class="fw-bold mb-1">Select Faculty to View Overview</h5>
            <p class="mb-0">Please choose a faculty member to see their ratings breakdown.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, inject, onUnmounted, computed, watch } from "vue";
import { useRoute } from "vue-router";
import Sidebar from "../components/Sidebar.vue";
import Navbar from "../components/Navbar.vue";
import CustomSelect from "../components/CustomSelect.vue";
import AiSparkleIcon from "../components/AiSparkleIcon.vue";
import SkeletonLoader from "../components/SkeletonLoader.vue";
import { useBootstrapModal } from "../composables/useBootstrapModal.js";
import api from "../services/api.js";
import { courseDepartments } from "../helpers/academic.js";

const can = inject("can");
const route = useRoute();

const user = ref(JSON.parse(localStorage.getItem("user") || "{}") || {});
const evaluateeType = ref('faculty');
const facultyList = ref([]);
const selectedFacultyId = ref("");
const results = ref(null);
const aiInsights = ref(null);
const loading = ref(false);
const loadingAi = ref(false);
const tableScrolled = ref(false);
const showAiOverlay = ref(false);
const isAiFullScreen = ref(false);
const aiError = ref(null);
const { modalEl: aiModalEl } = useBootstrapModal(showAiOverlay);

function onTableScroll(e) {
  tableScrolled.value = e.target.scrollTop > 0;
}

// New Filters
const searchQuery = ref("");
const selectedDepartment = ref("all");
// Small screens: collapse the filter row behind a toggle button
const showFilters = ref(false);
const selectedQuestionRating = ref("");

function visibleQuestions(questions = []) {
  if (!selectedQuestionRating.value) return questions || [];
  const countKey = `count_${selectedQuestionRating.value}`;
  return (questions || []).filter((question) => Number(question[countKey]) > 0);
}

// Per-subject ratings overview ("" = overall, no subject filter)
const subjectRatings = ref(null);
const selectedSubject = ref("");

const formatSubjectSummary = (average, evaluations) => {
  if (average == null) return "";
  const count = Number(evaluations) || 0;
  return ` · ${Number(average).toFixed(2)}/5 · ${count} ${count === 1 ? "evaluation" : "evaluations"}`;
};

const subjectSelectOptions = computed(() => [
  {
    label: `All subjects (Overall)${formatSubjectSummary(subjectRatings.value?.overall?.average, subjectRatings.value?.overall?.evaluations)}`,
    value: "",
  },
  ...(subjectRatings.value?.subjects || [])
    .filter((s) => s.evaluations > 0)
    .map((s) => ({
      label: `${s.subject_name ? `${s.subject_code} — ${s.subject_name}` : s.subject_code}${formatSubjectSummary(s.average, s.evaluations)}`,
      value: s.subject_code,
    })),
]);

const departmentOptions = computed(() => [
  { label: "All Departments", value: "all" },
  ...departments.value.map(d => ({ label: d, value: d }))
]);

// Department filter options come from the Course List (single source of
// truth) — never from faculty records, which can hold stale departments
// whose courses were deleted.
const departments = ref([]);

async function resetFilters() {
  searchQuery.value = "";
  selectedDepartment.value = "all";
  selectedFacultyId.value = "all";
  selectedSubject.value = "";
  subjectRatings.value = null;
  await loadResults();
}

function toggleAiOverlay() {
  showAiOverlay.value = !showAiOverlay.value;
  if (!showAiOverlay.value) isAiFullScreen.value = false;
}

function toggleAiFullScreen() {
  isAiFullScreen.value = !isAiFullScreen.value;
}

const filteredFacultyList = computed(() => {
  if (!facultyList.value) return [];
  
  let list = facultyList.value.filter(f => f && f.id);

  // Apply Department Filter (faculty only)
  if (evaluateeType.value === "faculty" && selectedDepartment.value && selectedDepartment.value !== "all") {
    list = list.filter(f =>
      f.department && f.department.trim() === selectedDepartment.value.trim()
    );
  }

  // Apply Search Filter
  const q = searchQuery.value ? searchQuery.value.trim().toLowerCase() : "";
  if (q) {
    list = list.filter(f => {
      const name = f.user?.name?.toLowerCase() || "";
      const dept = f.department?.toLowerCase() || "";
      return name.includes(q) || dept.includes(q);
    });
  }

  return list;
});

const canUseAiInsights = computed(
  () => user.value.role !== "faculty",
);

const sentimentTotal = computed(() => {
  const s = aiInsights.value?.sentiment;
  if (!s) return 0;
  return (Number(s.positive) || 0) + (Number(s.neutral) || 0) + (Number(s.negative) || 0);
});

const trendValue = computed(() => {
  const m = aiInsights.value?.metrics;
  if (!m?.previous_rating) return "N/A";
  const delta = Number(m.average_rating) - Number(m.previous_rating);
  return (delta > 0 ? "+" : "") + delta.toFixed(2);
});

const trendClass = computed(() => {
  const m = aiInsights.value?.metrics;
  if (!m?.previous_rating) return "ai-kpi__value--muted";
  return Number(m.average_rating) >= Number(m.previous_rating)
    ? "ai-kpi__value--emerald"
    : "ai-kpi__value--rose";
});

const facultyOptions = computed(() => {
  const allLabel = "All Faculty";
  return [
    { label: allLabel, value: "all" },
    ...filteredFacultyList.value.map((f) => {
      return {
        label: `${f.user?.name} (${f.department || "N/A"})`,
        value: f.id,
      };
    }),
  ];
});

async function fetchEvaluateesList() {
  try {
    // Only faculty lists are supported now (staff reports removed)
    const [facultyRes, coursesRes] = await Promise.all([
      api.get("/faculty/all"),
      api.get("/courses")
    ]);
    facultyList.value = facultyRes.data;
    departments.value = courseDepartments(coursesRes.data);
  } catch (e) {
    console.error("Error fetching evaluatees list:", e);
  }
}

function getTypeFromRoute() {
  return "faculty";
}

async function applyEvaluateeTypeFromRoute() {
  if (can("report.view.all") && user.value.role !== "faculty") {
    const type = getTypeFromRoute();
    if (evaluateeType.value !== type) {
      evaluateeType.value = type;
      selectedFacultyId.value = "all";
      selectedDepartment.value = "all";
      await fetchEvaluateesList();
      await loadResults();
    }
  }
}

watch(
  () => route.query.type,
  async () => {
    await applyEvaluateeTypeFromRoute();
  },
);

onMounted(async () => {
  if (can("report.view.all") && user.value.role !== "faculty") {
    evaluateeType.value = getTypeFromRoute();
    selectedFacultyId.value = "all";
    await fetchEvaluateesList();
    await loadResults();
  } else if (user.value.role === "faculty") {
    const res = await api.get("/faculty/all");
    const mine = (res.data || []).find((f) => f?.user_id === user.value?.id);
    if (mine) {
      selectedFacultyId.value = mine.id;
      evaluateeType.value = 'faculty';
      await loadSubjectRatings();
      // Deep link from the dashboard: /reports?subject=IT-SIA01
      const subject = route.query.subject;
      if (
        subject &&
        subjectRatings.value?.subjects?.some((s) => s.subject_code === subject && s.evaluations > 0)
      ) {
        selectedSubject.value = String(subject);
      }
      await loadResults();
    }
  }
});

async function handleDepartmentChange() {
  if (selectedFacultyId.value !== 'all') {
    const currentFaculty = facultyList.value.find(f => f.id === selectedFacultyId.value);
    if (currentFaculty && currentFaculty.department !== selectedDepartment.value && selectedDepartment.value !== 'all') {
      selectedFacultyId.value = 'all';
    }
  }
  selectedSubject.value = "";
  subjectRatings.value = null;
  await Promise.all([loadResults(), loadSubjectRatings()]);
}

async function handleFacultyChange() {
  selectedSubject.value = "";
  subjectRatings.value = null;
  await Promise.all([loadResults(), loadSubjectRatings()]);
}

onUnmounted(() => {});

async function loadResults() {
  if (!selectedFacultyId.value) return;
  loading.value = true;
  results.value = null;
  try {
    const params = { evaluatee_type: evaluateeType.value };
    if (evaluateeType.value === "faculty" && selectedDepartment.value && selectedDepartment.value !== "all") {
      params.department = selectedDepartment.value;
    }
    if (selectedSubject.value) {
      params.subject_code = selectedSubject.value;
    }
    const res = await api.get(`/evaluations/results/${selectedFacultyId.value}`, { params });

    results.value = res.data;
    aiInsights.value = null;

    await nextTick();
    drawChart();
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function loadSubjectRatings() {
  if (!selectedFacultyId.value || selectedFacultyId.value === "all") {
    subjectRatings.value = null;
    return;
  }
  try {
    const params = {};
    if (user.value.role !== "faculty") {
      params.faculty_id = selectedFacultyId.value;
    }
    const res = await api.get("/reports/my-subject-ratings", { params });
    subjectRatings.value = res.data;
  } catch (e) {
    console.error("Error fetching subject ratings:", e);
    subjectRatings.value = null;
  }
}

async function loadAiInsights(force = false) {
  if (!selectedFacultyId.value || !canUseAiInsights.value) return;
  loadingAi.value = true;
  aiInsights.value = null;
  aiError.value = null;
  try {
    const params = { evaluatee_type: evaluateeType.value };
    if (force) params.refresh = 1;
    if (evaluateeType.value === "faculty" && selectedDepartment.value && selectedDepartment.value !== "all") {
      params.department = selectedDepartment.value;
    }
    const res = await api.get(`/reports/ai-insights/${selectedFacultyId.value}`, { params });
    aiInsights.value = res.data;
    await nextTick();
    renderDashboardCharts();
  } catch (e) {
    console.error("AI insights load failed", e);
    aiError.value = e.response?.data?.message || "An unexpected error occurred while generating insights.";
  } finally {
    loadingAi.value = false;
  }
}

function renderDashboardCharts() {
  import("chart.js").then(({ Chart, registerables }) => {
    Chart.register(...registerables);

    const existingBar = Chart.getChart("dashboardBarChart");
    if (existingBar) existingBar.destroy();
    const existingPie = Chart.getChart("dashboardPieChart");
    if (existingPie) existingPie.destroy();

    const css = getComputedStyle(document.documentElement);
    const muted = css.getPropertyValue("--text-muted").trim() || "#59636e";
    const grid = css.getPropertyValue("--border-light").trim() || "#e3e8ee";
    const surface = css.getPropertyValue("--bg-card").trim() || "#ffffff";
    const ink = css.getPropertyValue("--text-main").trim() || "#1f2328";
    const fontFamily = css.getPropertyValue("--font-sans").trim() || "Arial, sans-serif";

    const tooltipStyle = {
      backgroundColor: ink,
      titleColor: surface,
      bodyColor: surface,
      padding: 10,
      cornerRadius: 8,
      displayColors: false,
      titleFont: { family: fontFamily, size: 11, weight: 600 },
      bodyFont: { family: fontFamily, size: 12, weight: 700 },
    };

    // 1. Criteria Bar Chart
    const ctxBar = document.getElementById("dashboardBarChart");
    if (ctxBar && results.value?.category_results?.length) {
      new Chart(ctxBar, {
        type: "bar",
        data: {
          labels: results.value.category_results.map((c) => c.category_name),
          datasets: [
            {
              label: "Average Rating",
              data: results.value.category_results.map((c) => Number(c.average_rating).toFixed(2)),
              backgroundColor: (context) => {
                const { ctx, chartArea } = context.chart;
                if (!chartArea) return "#4f46e5";
                const gradient = ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0);
                gradient.addColorStop(0, "#4f46e5");
                gradient.addColorStop(1, "#a855f7");
                return gradient;
              },
              borderRadius: 6,
              borderSkipped: false,
              barThickness: 26,
            },
          ],
        },
        options: {
          indexAxis: "y",
          responsive: true,
          maintainAspectRatio: false,
          layout: { padding: { right: 16 } },
          plugins: {
            legend: { display: false },
            tooltip: { ...tooltipStyle, callbacks: { label: (c) => ` Rating: ${c.parsed.x}` } },
          },
          scales: {
            x: {
              max: 5,
              beginAtZero: true,
              grid: { color: grid, drawTicks: false },
              border: { display: false },
              ticks: { color: muted, font: { family: fontFamily, size: 11 }, stepSize: 1, padding: 6 },
            },
            y: {
              grid: { display: false },
              border: { display: false },
              ticks: { color: muted, font: { family: fontFamily, size: 11 }, padding: 8 },
            },
          },
        },
      });
    }

    // 2. Sentiment Pie Chart
    const ctxPie = document.getElementById("dashboardPieChart");
    if (ctxPie && aiInsights.value && sentimentTotal.value > 0) {
      const sentiment = aiInsights.value.sentiment;
      const positivePct = Math.round(Number(sentiment.positive) || 0);
      new Chart(ctxPie, {
        type: "doughnut",
        data: {
          labels: ["Positive", "Neutral", "Negative"],
          datasets: [
            {
              data: [sentiment.positive, sentiment.neutral, sentiment.negative],
              backgroundColor: ["#0e9f6e", "#f59e0b", "#f05252"],
              borderColor: surface,
              borderWidth: 3,
              hoverOffset: 8,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: "72%",
          plugins: {
            legend: {
              position: "bottom",
              labels: {
                usePointStyle: true,
                pointStyle: "circle",
                boxWidth: 8,
                padding: 16,
                color: muted,
                font: { family: fontFamily, size: 11 },
              },
            },
            tooltip: { ...tooltipStyle, callbacks: { label: (c) => ` ${c.label}: ${c.parsed}%` } },
          },
        },
        plugins: [
          {
            id: "aiCenterLabel",
            afterDraw(chart) {
              const { ctx, chartArea } = chart;
              if (!chartArea) return;
              const x = (chartArea.left + chartArea.right) / 2;
              const y = (chartArea.top + chartArea.bottom) / 2;
              ctx.save();
              ctx.textAlign = "center";
              ctx.textBaseline = "middle";
              ctx.fillStyle = ink;
              ctx.font = `700 24px ${fontFamily}`;
              ctx.fillText(`${positivePct}%`, x, y - 7);
              ctx.fillStyle = muted;
              ctx.font = `600 10px ${fontFamily}`;
              ctx.fillText("POSITIVE", x, y + 14);
              ctx.restore();
            },
          },
        ],
      });
    }
  });
}

function getRatingStatus(rating) {
  if (rating >= 4.5) return "Excellent";
  if (rating >= 3.5) return "Strong";
  if (rating >= 2.5) return "Average";
  return "Needs Improvement";
}

function getRatingBadge(rating) {
  if (rating >= 4.5) return "bg-success text-white shadow-sm";
  if (rating >= 3.5) return "bg-info text-white shadow-sm";
  if (rating >= 2.5) return "bg-warning text-dark shadow-sm";
  return "bg-danger text-white shadow-sm";
}

function getAiTagClass(rating) {
  if (rating >= 4.5) return "ai-tag--excellent";
  if (rating >= 3.5) return "ai-tag--strong";
  if (rating >= 2.5) return "ai-tag--average";
  return "ai-tag--weak";
}

function getAiBarClass(rating) {
  if (rating >= 4.5) return "ai-rating__fill--excellent";
  if (rating >= 3.5) return "ai-rating__fill--strong";
  if (rating >= 2.5) return "ai-rating__fill--average";
  return "ai-rating__fill--weak";
}

function drawChart() {
  import("chart.js").then(({ Chart, registerables }) => {
    Chart.register(...registerables);
    const existing = Chart.getChart("reportChart");
    if (existing) existing.destroy();

    const ctx = document.getElementById("reportChart");
    if (!ctx || !results.value) return;

    const textColor = "#374151";
    const gridColor = "rgba(0, 0, 0, 0.1)";

    new Chart(ctx, {
      type: "bar",
      data: {
        labels: results.value.category_results.map((c) => c.category_name),
        datasets: [
          {
            label: "Average Rating",
            data: results.value.category_results.map((c) => Number(c.average_rating)),
            backgroundColor: "rgba(25, 25, 112, 0.82)",
            hoverBackgroundColor: "#191970",
            borderRadius: 6,
            borderSkipped: false,
            maxBarThickness: 26,
          },
        ],
      },
      options: {
        indexAxis: "y",
        layout: {
          padding: { top: 4, right: 12, bottom: 4, left: 4 },
        },
        scales: {
          x: {
            beginAtZero: true,
            max: 5,
            grid: { color: gridColor },
            ticks: { color: textColor, stepSize: 1 },
          },
          y: {
            grid: { display: false },
            ticks: {
              color: textColor,
              autoSkip: false,
              padding: 8,
              callback(value) {
                const words = String(this.getLabelForValue(value)).split(/\s+/);
                const lines = [];
                let line = "";

                words.forEach((word) => {
                  if (line && `${line} ${word}`.length > 19) {
                    lines.push(line);
                    line = word;
                  } else {
                    line = line ? `${line} ${word}` : word;
                  }
                });

                if (line) lines.push(line);
                return lines;
              },
            },
          },
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: "#ffffff",
            titleColor: "#1e293b",
            bodyColor: "#475569",
            padding: 12,
            borderColor: "rgba(0, 0, 0, 0.1)",
            borderWidth: 1,
            displayColors: false,
          },
        },
        responsive: true,
        maintainAspectRatio: false,
      },
    });
  });
}

function badgeClass(interpretation) {
  const map = {
    Excellent: "badge-excellent",
    "Very Good": "badge-verygood",
    Good: "badge-good",
    Fair: "badge-fair",
    Poor: "badge-poor",
  };
  return map[interpretation] || "";
}
</script>

<style scoped>
.search-pill-container {
  display: flex;
  align-items: center;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 0 16px;
  height: 40px;
  min-height: 40px;
  transition: all 0.3s ease;
}

[data-theme="dark"] .search-pill-container {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
}

.search-pill-container:focus-within {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(25, 25, 112, 0.15);
}

.search-icon {
  color: #191970; /* Vibrant Blue from reference */
  margin-right: 0.75rem;
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

[data-theme="dark"] .search-icon {
  color: #60a5fa;
}

.search-input-field {
  background: transparent;
  border: none;
  color: var(--text-main);
  width: 100%;
  font-size: 14px;
  font-weight: 500;
  outline: none;
}

[data-theme="dark"] .search-input-field {
  color: var(--text-main);
}

.search-input-field::placeholder {
  color: var(--text-muted);
  opacity: 0.7;
}

[data-theme="dark"] .search-input-field::placeholder {
  color: var(--text-muted);
  opacity: 0.7;
}

.refresh-pill-btn {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  background: var(--bg-card);
  color: var(--text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  cursor: pointer;
}

.refresh-pill-btn:hover {
  background: var(--primary);
  color: white !important;
  border-color: var(--primary);
}

.refresh-pill-btn:hover i {
  color: white !important;
}

[data-theme="dark"] .refresh-pill-btn {
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(255, 255, 255, 0.05);
  color: rgba(255, 255, 255, 0.6);
}

.refresh-pill-btn:hover {
  background: rgba(25, 25, 112, 0.1);
  color: var(--primary);
  border-color: var(--primary);
  transform: rotate(-30deg);
}

/* ── Responsive filter bar (collapse behind an icon on small screens) ── */
.reports-filter-body {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  overflow: visible !important;
}

.filter-toggle-btn {
  display: none;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.6rem 0.9rem;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md, 8px);
  background: var(--bg-card);
  color: var(--text-main);
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.15s ease, color 0.15s ease;
}

.filter-toggle-btn:hover,
.filter-toggle-btn:focus-visible {
  border-color: var(--primary);
  color: var(--primary);
}

.reports-filters {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem; /* same as the old gap-3 */
  overflow: visible !important;
}

.filter-slot--search {
  width: 220px;
}

.filter-slot--dept {
  width: 200px;
}

.filter-slot--faculty {
  width: 350px;
}

@media (max-width: 991.98px) {
  .filter-toggle-btn {
    display: flex;
  }

  .reports-filters {
    display: none;
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }

  .reports-filters.is-open {
    display: flex;
  }

  .filter-slot--search,
  .filter-slot--dept,
  .filter-slot--faculty {
    width: 100%;
  }

  .reports-filters .refresh-pill-btn {
    align-self: flex-start;
  }

  .reports-filters .ms-auto {
    margin-left: 0 !important;
    align-self: flex-start;
  }
}

.ai-fab-container {
  position: fixed;
  bottom: 2.5rem;
  right: 2.5rem;
  z-index: 1040;
}

.ai-fab {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: #ffffff;
  color: #4285f4;
  border: 1px solid rgba(60, 64, 67, 0.14);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.75rem;
  box-shadow:
    0 1px 3px rgba(60, 64, 67, 0.16),
    0 8px 20px -6px rgba(66, 133, 244, 0.4),
    0 18px 36px -12px rgba(155, 114, 204, 0.45);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease;
  cursor: pointer;
  position: relative;
}

.ai-fab .ai-sparkle-icon {
  transition: transform 0.25s ease;
}

.ai-fab:hover {
  transform: translateY(-3px);
  background: #ffffff;
  border-color: rgba(66, 133, 244, 0.45);
  box-shadow:
    0 2px 6px rgba(60, 64, 67, 0.18),
    0 12px 28px -6px rgba(66, 133, 244, 0.5),
    0 22px 44px -12px rgba(217, 101, 112, 0.4);
}

.ai-fab:hover .ai-sparkle-icon {
  transform: scale(1.12) rotate(15deg);
}

.ai-fab:active {
  transform: translateY(0) scale(0.96);
}

.ai-badge {
  position: absolute;
  bottom: calc(100% + 12px);
  right: 0;
  background: #1f1f1f;
  color: #ffffff;
  font-size: 0.75rem;
  font-weight: 500;
  letter-spacing: 0.01em;
  padding: 0.5rem 0.9rem;
  border-radius: 999px;
  box-shadow:
    0 2px 8px rgba(60, 64, 67, 0.28),
    0 10px 24px -10px rgba(60, 64, 67, 0.5);
  white-space: nowrap;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ai-badge::after {
  content: "";
  position: absolute;
  top: 100%;
  right: 22px;
  border-width: 6px 6px 0;
  border-style: solid;
  border-color: #1f1f1f transparent transparent;
}

/* ── Premium AI theme: modal frame ─────────────────────────── */
.ai-modal {
  overflow: hidden;
  box-shadow: 0 24px 64px -28px rgba(17, 14, 46, 0.4) !important;
}

[data-theme="dark"] .ai-modal {
  box-shadow: 0 24px 64px -28px rgba(0, 0, 0, 0.85) !important;
}

.ai-modal::before {
  content: "";
  position: absolute;
  inset: 0 0 auto 0;
  height: 3px;
  background: linear-gradient(90deg, #4f46e5, #7c3aed 40%, #a855f7 68%, #2563eb);
  z-index: 4;
  pointer-events: none;
}

.ai-modal-header {
  background: linear-gradient(180deg, rgba(124, 58, 237, 0.07), rgba(124, 58, 237, 0));
  border-bottom: 1px solid var(--border-light);
}

[data-theme="dark"] .ai-modal-header {
  background: linear-gradient(180deg, rgba(139, 92, 246, 0.12), rgba(139, 92, 246, 0));
}

.ai-modal-title {
  font-size: 1.05rem;
  font-weight: 700 !important;
  letter-spacing: -0.01em;
}

.ai-modal-sub {
  margin: 2px 0 0;
  font-size: 0.72rem;
  color: var(--text-muted);
}

.ai-orb {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #4f46e5, #7c3aed 55%, #a855f7);
  box-shadow: 0 6px 16px -6px rgba(124, 58, 237, 0.75);
}

.ai-orb--lg {
  width: 64px;
  height: 64px;
  border-radius: 18px;
}

.ai-orb--xl {
  width: 92px;
  height: 92px;
  border-radius: 26px;
  box-shadow: 0 12px 30px -10px rgba(124, 58, 237, 0.75);
}

.ai-orb--pulse {
  animation: aiOrbPulse 1.8s ease-in-out infinite;
}

@keyframes aiOrbPulse {
  0%,
  100% {
    transform: scale(1);
    box-shadow: 0 6px 16px -6px rgba(124, 58, 237, 0.7);
  }
  50% {
    transform: scale(1.06);
    box-shadow: 0 12px 28px -8px rgba(124, 58, 237, 0.95);
  }
}

.ai-btn-grad {
  background: linear-gradient(135deg, #4f46e5, #7c3aed) !important;
  border: 1px solid transparent !important;
  color: #ffffff !important;
  font-weight: 600 !important;
  padding: 0.35rem 0.95rem !important;
  box-shadow: 0 4px 14px -5px rgba(79, 70, 229, 0.65) !important;
  transition:
    filter 0.15s ease,
    box-shadow 0.15s ease;
}

.ai-btn-grad:hover,
.ai-btn-grad:focus-visible {
  color: #ffffff !important;
  filter: brightness(1.1);
  box-shadow: 0 6px 18px -5px rgba(124, 58, 237, 0.75) !important;
}

.ai-btn-grad--lg {
  padding: 0.75rem 1.6rem !important;
  font-size: 0.95rem !important;
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  margin-top: 0.5rem;
}

.ai-btn-ghost {
  width: 32px;
  height: 32px;
  padding: 0 !important;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-card);
  border: 1px solid var(--border-color) !important;
  color: var(--text-muted);
  border-radius: 8px !important;
  transition:
    color 0.15s ease,
    border-color 0.15s ease;
}

.ai-btn-ghost:hover,
.ai-btn-ghost:focus-visible {
  color: #7c3aed;
  border-color: #7c3aed;
  background: var(--bg-card);
}

/* ── Premium AI theme: modal body canvas ───────────────────── */
.ai-modal-body {
  --ai-tint: rgba(124, 58, 237, 0.16);
  background:
    radial-gradient(900px 320px at 8% -10%, rgba(124, 58, 237, 0.1), transparent 60%),
    radial-gradient(760px 300px at 105% 0%, rgba(37, 99, 235, 0.09), transparent 55%),
    var(--bg-card);
}

[data-theme="dark"] .ai-modal-body {
  --ai-tint: rgba(167, 139, 250, 0.22);
  background:
    radial-gradient(900px 320px at 8% -10%, rgba(139, 92, 246, 0.16), transparent 60%),
    radial-gradient(760px 300px at 105% 0%, rgba(59, 130, 246, 0.12), transparent 55%),
    var(--bg-card);
}

.ai-modal-body::-webkit-scrollbar {
  width: 8px;
}
.ai-modal-body::-webkit-scrollbar-track {
  background: transparent;
}
.ai-modal-body::-webkit-scrollbar-thumb {
  background: rgba(124, 58, 237, 0.35);
  border-radius: 10px;
}
.ai-modal-body::-webkit-scrollbar-thumb:hover {
  background: rgba(124, 58, 237, 0.55);
}

/* ── Premium AI theme: loading / error / empty states ──────── */
.ai-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 2.5rem 1rem;
}

.ai-state__title {
  margin: 1.1rem 0 0.4rem;
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-main);
}

.ai-state__title--lg {
  font-size: 1.6rem;
  letter-spacing: -0.02em;
}

.ai-state__text {
  max-width: 430px;
  margin: 0 auto 0.5rem;
  font-size: 0.875rem;
  line-height: 1.6;
  color: var(--text-muted);
}

.ai-state__text--wide {
  max-width: 540px;
  margin-bottom: 1.25rem;
}

.ai-state__icon {
  width: 72px;
  height: 72px;
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
}

.ai-state__icon--danger {
  background: var(--badge-danger-bg);
  color: var(--badge-danger-text);
}

/* ── Premium AI theme: KPI cards ───────────────────────────── */
.ai-kpi {
  --ai-c1: #4f46e5;
  --ai-c2: #a855f7;
  --ai-glow: rgba(124, 58, 237, 0.55);
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 0.9rem;
  padding: 1.1rem 1.15rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  overflow: hidden;
  transition: border-color 0.15s ease;
}

.ai-kpi::after {
  content: "";
  position: absolute;
  top: -48px;
  right: -48px;
  width: 120px;
  height: 120px;
  border-radius: 50%;
  background: radial-gradient(circle, var(--ai-tint), transparent 70%);
  pointer-events: none;
}

.ai-kpi:hover {
  border-color: rgba(124, 58, 237, 0.4);
}

.ai-kpi--violet {
  --ai-c1: #4f46e5;
  --ai-c2: #a855f7;
  --ai-glow: rgba(124, 58, 237, 0.55);
}

.ai-kpi--blue {
  --ai-c1: #2563eb;
  --ai-c2: #38bdf8;
  --ai-glow: rgba(56, 189, 248, 0.5);
}

.ai-kpi--emerald {
  --ai-c1: #059669;
  --ai-c2: #34d399;
  --ai-glow: rgba(52, 211, 153, 0.5);
}

.ai-kpi--amber {
  --ai-c1: #d97706;
  --ai-c2: #fbbf24;
  --ai-glow: rgba(251, 191, 36, 0.55);
}

.ai-kpi__icon {
  width: 42px;
  height: 42px;
  border-radius: 11px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.05rem;
  color: #ffffff;
  background: linear-gradient(135deg, var(--ai-c1), var(--ai-c2));
  box-shadow: 0 6px 16px -6px var(--ai-glow);
}

.ai-kpi__body {
  min-width: 0;
}

.ai-kpi__label {
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.09em;
  text-transform: uppercase;
  color: var(--text-muted);
}

.ai-kpi__value {
  margin-top: 0.15rem;
  font-size: 1.65rem;
  line-height: 1.15;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--text-main);
  font-variant-numeric: tabular-nums;
}

.ai-kpi__value--emerald {
  color: var(--success);
}

.ai-kpi__value--rose {
  color: var(--danger);
}

.ai-kpi__value--muted {
  color: var(--text-muted);
}

.ai-kpi__hint {
  margin-top: 0.15rem;
  font-size: 0.68rem;
  color: var(--text-muted);
}

/* ── Premium AI theme: surfaces (charts + tables) ──────────── */
.ai-surface {
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  overflow: hidden;
}

.ai-surface__head {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  padding: 1.05rem 1.2rem;
  border-bottom: 1px solid var(--border-light);
  background: linear-gradient(180deg, rgba(124, 58, 237, 0.055), rgba(124, 58, 237, 0));
}

.ai-surface__icon {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
}

.ai-chip--violet {
  background: linear-gradient(135deg, rgba(79, 70, 229, 0.16), rgba(168, 85, 247, 0.16));
  color: #7c3aed;
}

.ai-chip--blue {
  background: rgba(37, 99, 235, 0.12);
  color: #2563eb;
}

.ai-chip--emerald {
  background: rgba(14, 159, 110, 0.14);
  color: var(--success);
}

[data-theme="dark"] .ai-chip--violet {
  color: #a78bfa;
}

[data-theme="dark"] .ai-chip--blue {
  color: #60a5fa;
}

[data-theme="dark"] .ai-chip--emerald {
  color: #34d399;
}

.ai-surface__title {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: var(--text-main);
}

.ai-surface__sub {
  margin: 0.1rem 0 0;
  font-size: 0.7rem;
  color: var(--text-muted);
}

.ai-surface__body {
  flex: 1;
  padding: 1.1rem 1.2rem;
}

.ai-surface__body--flush {
  padding: 0;
}

.ai-surface__body--center {
  display: flex;
  align-items: center;
  justify-content: center;
}

.ai-chart-box {
  position: relative;
  height: 240px;
}

.ai-viz-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.6rem;
  padding: 2.5rem 1rem;
  font-size: 0.78rem;
  color: var(--text-muted);
}

.ai-viz-empty i {
  font-size: 1.75rem;
  opacity: 0.35;
}

/* ── Premium AI theme: tables ──────────────────────────────── */
.ai-modal .ai-table thead th {
  background: transparent !important;
  background-color: transparent !important;
  font-size: 0.68rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.07em;
  color: var(--text-muted) !important;
  padding-top: 0.8rem !important;
  padding-bottom: 0.8rem !important;
}

.ai-modal .ai-table tbody td {
  padding-top: 0.85rem !important;
  padding-bottom: 0.85rem !important;
}

.ai-rating {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.ai-rating__val {
  min-width: 2.4rem;
  font-size: 0.86rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: var(--text-main);
}

.ai-rating__track {
  flex: 1;
  min-width: 64px;
  max-width: 140px;
  height: 6px;
  border-radius: 999px;
  background: var(--bg-light);
  overflow: hidden;
}

.ai-rating__fill {
  display: block;
  height: 100%;
  border-radius: 999px;
  transition: width 0.4s ease;
}

.ai-rating__fill--excellent {
  background: linear-gradient(90deg, #059669, #34d399);
}

.ai-rating__fill--strong {
  background: linear-gradient(90deg, #2563eb, #60a5fa);
}

.ai-rating__fill--average {
  background: linear-gradient(90deg, #d97706, #fbbf24);
}

.ai-rating__fill--weak {
  background: linear-gradient(90deg, #dc2626, #fb7185);
}

.ai-tag {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.28rem 0.65rem;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  border-radius: 999px;
  white-space: nowrap;
}

.ai-tag--excellent {
  background: var(--badge-success-bg);
  color: var(--badge-success-text);
}

.ai-tag--strong {
  background: var(--badge-info-bg);
  color: var(--badge-info-text);
}

.ai-tag--average {
  background: var(--badge-warning-bg);
  color: var(--badge-warning-text);
}

.ai-tag--weak {
  background: var(--badge-danger-bg);
  color: var(--badge-danger-text);
}

.ai-metric-name {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  color: var(--text-main);
}

.ai-metric-name__dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
  background: linear-gradient(135deg, #4f46e5, #a855f7);
}

.ai-chip-plain {
  display: inline-flex;
  align-items: center;
  padding: 0.22rem 0.6rem;
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  border-radius: 999px;
  background: rgba(79, 70, 229, 0.1);
  color: #4f46e5;
}

[data-theme="dark"] .ai-chip-plain {
  background: rgba(129, 140, 248, 0.16);
  color: #a5b4fc;
}

/* ── Premium AI theme: narrative panels ────────────────────── */
.ai-note {
  --ai-note-grad: linear-gradient(135deg, #4f46e5, #a855f7);
  --ai-note: #7c3aed;
  position: relative;
  padding: 1.35rem 1.35rem 1.4rem;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  overflow: hidden;
}

.ai-note::before {
  content: "";
  position: absolute;
  inset: 0 0 auto 0;
  height: 3px;
  background: var(--ai-note-grad);
}

.ai-note--emerald {
  --ai-note-grad: linear-gradient(90deg, #059669, #34d399);
  --ai-note: #0e9f6e;
}

.ai-note--rose {
  --ai-note-grad: linear-gradient(90deg, #e11d48, #fb7185);
  --ai-note: #f05252;
}

.ai-note--violet {
  --ai-note-grad: linear-gradient(90deg, #4f46e5, #a855f7);
  --ai-note: #7c3aed;
}

.ai-note__head {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  margin-bottom: 0.95rem;
}

.ai-note__icon {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.78rem;
  color: #ffffff;
  background: var(--ai-note-grad);
  box-shadow: 0 4px 12px -5px var(--ai-note);
}

.ai-note__title {
  margin: 0;
  font-size: 0.86rem;
  font-weight: 700;
  color: var(--ai-note);
}

.ai-note__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.ai-note__list li {
  display: flex;
  gap: 0.65rem;
  font-size: 0.82rem;
  line-height: 1.55;
  font-weight: 500;
  color: var(--text-main);
}

.ai-note__bullet {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 0.5rem;
  background: var(--ai-note);
  opacity: 0.75;
}

.ai-note__empty {
  margin: 0;
  font-size: 0.78rem;
  font-style: italic;
  color: var(--text-muted);
}

.suggestion-scroll::-webkit-scrollbar {
  width: 6px;
}
.suggestion-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.suggestion-scroll::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.1);
  border-radius: 10px;
}

.x-small {
  font-size: 0.65rem;
}

.fw-500 {
  font-weight: 500;
}

.fw-600 {
  font-weight: 500;
}

.fw-800 {
  font-weight: 500;
}

.dashboard-grid {
  animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.tabs-premium-container {
  display: inline-flex;
  background: var(--bg-light);
  border: 1px solid var(--border-light);
  padding: 6px;
  border-radius: 50px;
  gap: 8px;
}

.tab-btn-premium {
  border: none;
  background: transparent;
  padding: 10px 24px;
  border-radius: 50px;
  font-weight: 500;
  color: var(--text-muted);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  font-size: 0.95rem;
  display: flex;
  align-items: center;
}

.tab-btn-premium:hover {
  color: var(--primary);
  background: rgba(25, 25, 112, 0.05);
}

.tab-btn-premium.active {
  background: #191970;
  color: white;
  box-shadow: 0 4px 12px rgba(25, 25, 112, 0.25);
}

/* ── Mobile-friendly report layout (faculty) ── */
.reports-summary-row {
  margin-left: 0;
  margin-right: 0;
}

.report-final-score {
  font-size: clamp(2.25rem, 10vw, 3.5rem);
  line-height: 1.1;
}

.report-chart-body {
  position: relative;
  min-height: 200px;
  height: 220px;
}

@media (min-width: 768px) {
  .report-chart-body {
    height: 200px;
    min-height: 180px;
  }
}

.reports-mobile-categories {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.reports-category-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--card-radius);
  padding: 1rem;
}

.reports-category-card-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--border-color);
}

.reports-question-list {
  display: grid;
  gap: 0.4rem;
  margin: 0.55rem 0 0;
  padding-left: 1.15rem;
  color: var(--text-muted);
  font-size: 0.75rem;
  line-height: 1.45;
}

.reports-question-filter {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.6rem;
  margin-bottom: 0.75rem;
  color: var(--text-muted);
  font-size: 0.8rem;
}

.reports-question-filter .form-select {
  width: auto;
  min-width: 130px;
  padding-top: 0.35rem;
  padding-bottom: 0.35rem;
  font-size: 0.8rem;
}

.reports-question-empty {
  margin: 0.5rem 0 0;
  color: var(--text-muted);
  font-size: 0.75rem;
}

.reports-question-item {
  padding-left: 0.15rem;
}

.reports-question-rating {
  display: block;
  margin-top: 0.15rem;
  color: var(--text-main);
  font-size: 0.7rem;
  font-weight: 600;
}

.reports-question-rating-counts {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  margin-top: 0.25rem;
}

.reports-question-rating-counts span {
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
  background: var(--bg-light);
  color: var(--text-muted);
  font-size: 0.65rem;
  line-height: 1.4;
}

.reports-freq-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.35rem;
  margin-bottom: 0.75rem;
}

.reports-freq-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.15rem;
  padding: 0.35rem 0.2rem;
  background: var(--bg-light);
  border-radius: 6px;
}

.reports-freq-val {
  font-weight: 500;
  font-size: 0.95rem;
}

.reports-freq-lbl {
  font-size: 0.65rem;
  font-weight: 500;
  color: var(--text-muted);
  text-transform: uppercase;
}

.reports-category-card-foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 0.5rem;
  border-top: 1px dashed var(--border-color);
}

/* Sticky Table Header with Glassmorphism */
.reports-table-scroll { max-height: 60vh; overflow-y: auto; border-radius: 8px; }
table { border-collapse: separate; border-spacing: 0; }
thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: var(--bg-card);
  transition: all 0.2s ease;
  box-shadow: none;
  border-right: 1px solid var(--border-light);
}
thead th:last-child { border-right: none; }
.glass-header th {
  background: rgba(255, 255, 255, 0.6);
  backdrop-filter: blur(12px) saturate(180%);
  -webkit-backdrop-filter: blur(12px) saturate(180%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.3);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}
[data-theme="dark"] .glass-header th {
  background: rgba(30, 41, 59, 0.6);
  border-bottom: 1px solid rgba(148, 163, 184, 0.1);
}

/* ── Performance by Subject ─────────────── */
.subject-select-slot {
  min-width: 260px;
}

.reports-content-area {
  padding: 24px;
}

.subject-select-slot :deep(.custom-select-trigger) {
  gap: 0.5rem;
}

.subject-select-slot :deep(.selected-text) {
  flex: 1;
  min-width: 0;
  overflow: visible;
  text-overflow: clip;
  white-space: normal;
  line-height: 1.35;
}

.subject-select-slot :deep(.arrow) {
  flex-shrink: 0;
  margin-left: 0;
}

.subject-select-slot :deep(.custom-select-options li) {
  white-space: normal;
}

.subject-select-slot :deep(.custom-select-options .text-truncate) {
  overflow: visible !important;
  text-overflow: clip !important;
  white-space: normal !important;
  overflow-wrap: anywhere;
}

@media (max-width: 767.98px) {
  .subject-select-slot {
    min-width: 0;
    width: 100%;
  }
}

@media print {
  .reports-mobile-categories {
    display: none !important;
  }

  .reports-table-card.d-none.d-md-block {
    display: block !important;
  }
}

@media (max-width: 767.98px) {
  .ai-fab-container {
    bottom: 1.25rem;
    right: 1.25rem;
  }

  .ai-fab {
    width: 56px;
    height: 56px;
    font-size: 1.5rem;
  }
}

@media (max-width: 575.98px) {
  .content-area.reports-content-area {
    padding: 16px 14px 24px !important;
  }

  .reports-question-filter {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.35rem;
  }

  .reports-question-filter .form-select {
    width: 100%;
  }

  .reports-results {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
  }

  .reports-summary-row {
    gap: 0.75rem;
    margin-bottom: 0 !important;
  }

  .reports-summary-row > [class*="col-"] {
    padding-right: 0;
    padding-left: 0;
  }

  .reports-summary-row .stat-card,
  .reports-summary-row > div > .card,
  .subject-overview-card,
  .reports-category-card {
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-card);
    box-shadow: none;
  }

  .reports-summary-row .stat-card {
    min-height: 148px;
    justify-content: center;
    padding: 1rem !important;
  }

  .reports-summary-row .stat-label {
    margin-bottom: 0.4rem !important;
    color: var(--text-muted);
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
  }

  .report-final-score {
    margin-bottom: 0.5rem !important;
    color: var(--success) !important;
    font-size: 2.65rem;
    font-weight: 700;
    letter-spacing: -0.055em;
    line-height: 1.05;
  }

  .reports-summary-row .badge {
    align-self: center;
    padding: 0.38rem 0.8rem !important;
    font-size: 0.76rem;
    font-weight: 600;
  }

  .reports-summary-row > div > .card {
    overflow: hidden;
    border-radius: 12px;
  }

  .reports-summary-row .card-header {
    padding: 0.8rem 1rem !important;
    border-bottom: 1px solid var(--border-color) !important;
    background: transparent;
  }

  .reports-summary-row .card-header h6 {
    font-size: 0.92rem;
    font-weight: 600 !important;
  }

  .report-chart-body {
    min-height: 248px;
    height: 248px;
    padding: 0.85rem 0.9rem 0.65rem !important;
  }

  .subject-overview-card {
    position: relative;
    z-index: 5;
    overflow: visible;
    margin-bottom: 0 !important;
  }

  .subject-overview-card > .card-header {
    align-items: stretch !important;
    flex-direction: column;
    padding: 0.9rem !important;
    border-bottom: 1px solid var(--border-color) !important;
    background: transparent;
  }

  .subject-overview-card > .card-header h6 {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.2rem 0.4rem;
    font-size: 0.92rem;
    line-height: 1.4;
  }

  .reports-mobile-categories {
    gap: 0.75rem;
    margin-bottom: 0 !important;
  }

  .reports-category-card {
    padding: 1rem;
  }

  .reports-category-card-head {
    margin-bottom: 0.8rem;
    padding-bottom: 0.8rem;
  }

  .reports-category-card-head .fw-bold {
    font-size: 0.95rem;
    line-height: 1.45;
  }

  .reports-freq-grid {
    gap: 0.4rem;
    margin-bottom: 0.8rem;
  }

  .reports-freq-cell {
    min-height: 54px;
    justify-content: center;
    gap: 0.2rem;
    padding: 0.4rem 0.2rem;
    border-radius: 8px;
  }

  .reports-freq-val {
    font-size: 0.9rem;
  }

  .reports-category-card-foot {
    padding-top: 0.7rem;
    border-top-style: solid;
  }
}
</style>
