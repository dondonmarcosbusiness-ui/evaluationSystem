# Faculty Evaluation System - Flow Diagram

## System Overview

This is a comprehensive faculty evaluation system built with Laravel that allows students to evaluate faculty members across different courses and subjects.

---

## Main System Architecture

```mermaid
graph TB
    subgraph Users["👥 Users & Authentication"]
        Admin["Admin User"]
        Student["Student User"]
        Faculty["Faculty User"]
        GoogleAuth["Google OAuth"]
    end

    subgraph Auth["🔐 Authentication Layer"]
        Login["Email/Password Login"]
        GoogleCallback["Google Callback"]
        Sanctum["Laravel Sanctum<br/>API Tokens"]
    end

    subgraph Core["⚙️ Core System"]
        Courses["Courses & Subjects"]
        Sections["Sections/Classes"]
        Enrollments["Student Enrollments"]
        Assignments["Faculty Assignments"]
    end

    subgraph Evaluation["📋 Evaluation Process"]
        Questions["Evaluation Questions"]
        Categories["Question Categories"]
        EvalForm["Evaluation Form"]
        Answers["Student Answers"]
    end

    subgraph Data["💾 Data Storage"]
        Database[(Database)]
        Cache["Cache Storage"]
    end

    subgraph AI["🤖 AI Processing"]
        AiService["AI Service<br/>Gemini/Claude"]
        Analysis["Evaluation Analysis"]
    end

    subgraph Output["📊 Reports & Output"]
        Reports["Faculty Reports"]
        Analytics["Analytics Dashboard"]
        Export["Export Data"]
    end

    Users -->|Authenticate| Auth
    Auth -->|Tokens| Sanctum
    Sanctum -->|Authorize| Core
    Core -->|Setup| Evaluation
    Evaluation -->|Store| Data
    Data -->|Retrieve| AI
    AI -->|Generate Insights| Output
```

---

## Student Evaluation Workflow

```mermaid
sequenceDiagram
    participant S as Student
    participant App as Application
    participant DB as Database
    participant AI as AI Service

    S->>App: Login (Email/Google OAuth)
    App->>DB: Verify Credentials
    DB-->>App: Token
    App-->>S: Authenticated

    S->>App: View Evaluations
    App->>DB: Get Assigned Faculty
    DB-->>App: Faculty List
    App-->>S: Display Faculty & Courses

    S->>App: Start Evaluation
    App->>DB: Get Questions & Categories
    DB-->>App: Questions Data
    App-->>S: Display Evaluation Form

    S->>App: Submit Answers
    App->>DB: Save Answers
    DB-->>App: Confirmation

    Note over App: On Evaluation Submission
    App->>AI: Send Evaluation Data
    AI-->>App: Analysis Result
    App->>DB: Store AI Analysis
    App-->>S: Submission Confirmed
```

---

## Admin Management Workflow

```mermaid
flowchart LR
    Admin["👤 Admin Portal"]
    
    Admin -->|Manage| Faculty["Faculty Members"]
    Admin -->|Manage| Students["Student Accounts"]
    Admin -->|Setup| Questions["Evaluation Questions"]
    Admin -->|Setup| Categories["Question Categories"]
    Admin -->|Manage| Assignments["Faculty Assignments"]
    Admin -->|View| Reports["Reports & Analytics"]
    Admin -->|Configure| Settings["System Settings"]
    
    Faculty -->|Bulk Import| Import["📥 Import Faculty"]
    Faculty -->|Update Status| Status["Active/Inactive"]
    Faculty -->|Bulk Operations| Bulk["Delete/Archive"]
    
    Students -->|Bulk Import| ImportStud["📥 Import Students"]
    Students -->|Assign| Enrollment["Enroll in Sections"]
    
    Questions -->|Create/Edit| Content["Question Content"]
    Questions -->|Categorize| Org["Organize by Category"]
    
    Reports -->|Generate| Faculty_Reports["Faculty Performance"]
    Reports -->|Analyze| Trends["Evaluation Trends"]
    Reports -->|Export| Download["Download Reports"]
```

---

## Database Entity Relationships

```mermaid
erDiagram
    USER ||--o{ EVALUATION : submits
    USER ||--o{ STUDENT : is
    USER ||--o{ FACULTY : is
    
    STUDENT ||--o{ ENROLLMENT : has
    FACULTY ||--o{ FACULTY_ASSIGNMENT : assigned
    COURSE ||--o{ SUBJECT : contains
    SECTION ||--o{ ENROLLMENT : has
    
    ENROLLMENT ||--o{ EVALUATION : triggers
    FACULTY_ASSIGNMENT ||--o{ EVALUATION : relates
    
    EVALUATION ||--o{ ANSWER : contains
    EVALUATION ||--o{ CATEGORY : evaluates
    
    QUESTION ||--o{ ANSWER : answered_by
    CATEGORY ||--o{ QUESTION : contains
    
    ROLE ||--o{ PERMISSION : has
    USER ||--o{ ROLE : assigned
    
    SECTION ||--o{ SUBJECT : teaches
    FACULTY_ASSIGNMENT ||--o{ SUBJECT : assigns

    USER {
        uuid id
        string email
        string name
        timestamp created_at
    }

    STUDENT {
        uuid id
        uuid user_id
        string course
        string section
        uuid section_id
    }

    FACULTY {
        uuid id
        uuid user_id
        string designation
        string department
    }

    EVALUATION {
        uuid id
        uuid student_id
        uuid faculty_id
        string semester
        string academic_year
        string subject_code
        text comments
        json ai_analysis
        timestamp submitted_at
    }

    QUESTION {
        uuid id
        uuid category_id
        text question_text
        text question_text_tl
    }

    ANSWER {
        uuid id
        uuid evaluation_id
        uuid question_id
        integer rating
        text comment
    }

    CATEGORY {
        uuid id
        string name
        string description
    }

    ENROLLMENT {
        uuid id
        uuid student_id
        uuid section_id
        string status
    }

    SECTION {
        uuid id
        string section_code
        string year_level
    }

    SUBJECT {
        uuid id
        string subject_code
        string subject_name
        uuid course_id
    }

    COURSE {
        uuid id
        string course_code
        string course_name
    }

    FACULTY_ASSIGNMENT {
        uuid id
        uuid faculty_id
        uuid subject_id
        uuid section_id
    }

    ROLE {
        uuid id
        string name
    }

    PERMISSION {
        uuid id
        string name
        uuid role_id
    }
```

---

## Key Features & Processes

### 1. **Authentication** 🔐
- Email/Password Login
- Google OAuth Integration
- Laravel Sanctum API Tokens
- Account Linking

### 2. **Faculty Management** 👨‍🏫
- Import Faculty in Bulk
- Update Faculty Status
- Assign to Subjects/Sections
- Manage Faculty Roles & Permissions

### 3. **Student Management** 👨‍🎓
- Import Student Records
- Enroll in Sections
- Track Enrollments
- Manage Student Roles

### 4. **Evaluation System** 📋
- Create Questions by Category
- Distribute Evaluations
- Collect Student Responses
- Calculate Ratings
- Store Comments

### 5. **AI Analysis** 🤖
- Process Evaluation Responses
- Generate Insights using Gemini/Claude
- Sentiment Analysis
- Performance Analytics

### 6. **Reporting** 📊
- Faculty Performance Reports
- Evaluation Statistics
- Trend Analysis
- Export Data (Excel, PDF)

---

## API Endpoints Structure

```
POST   /api/login                    → Student/Admin Login
GET    /api/auth/google              → Google OAuth Redirect
POST   /api/auth/google/register     → Google Registration
GET    /api/courses                  → Fetch Courses
GET    /api/settings                 → Get System Settings
POST   /api/settings                 → Update Settings

FACULTY MANAGEMENT
GET    /api/faculty                  → List Faculty
POST   /api/faculty                  → Create Faculty
PUT    /api/faculty/{id}             → Update Faculty
DELETE /api/faculty/{id}             → Delete Faculty
POST   /api/faculty/import           → Bulk Import

STUDENTS
GET    /api/students/all             → List All Students
POST   /api/enrollment               → Create Enrollment

EVALUATIONS
GET    /api/evaluations              → Get My Evaluations
GET    /api/evaluations/{id}         → Get Evaluation Details
POST   /api/evaluations              → Submit Evaluation
POST   /api/evaluations/assign       → Assign Evaluation

QUESTIONS
GET    /api/questions                → Get Evaluation Questions
GET    /api/categories               → Get Question Categories

REPORTS
GET    /api/reports/faculty/{id}     → Faculty Report
GET    /api/reports/analytics        → System Analytics
POST   /api/reports/export           → Export Report

PERMISSIONS & ROLES
GET    /api/roles                    → List Roles
GET    /api/permissions              → List Permissions
POST   /api/user-roles               → Assign Role to User
```

---

## Data Flow: From Evaluation to Report

```mermaid
graph TD
    A["Student Logs In"]
    B["Views Assigned Faculty"]
    C["Opens Evaluation Form"]
    D["Answers Questions & Comments"]
    E["Submits Evaluation"]
    
    F["Evaluate Data Stored"]
    G["AI Service Processes"]
    H["Generate Analysis"]
    I["Store AI Results"]
    
    J["Admin Views Reports"]
    K["Analytics Dashboard"]
    L["Export Report"]
    M["Archive/Action Items"]
    
    A --> B --> C --> D --> E
    E --> F --> G --> H --> I
    I --> J --> K --> L --> M
    
    style A fill:#e1f5ff
    style E fill:#fff3e0
    style I fill:#f3e5f5
    style J fill:#e8f5e9
    style M fill:#ffebee
```

---

## System Technologies

| Layer | Technology |
|-------|-----------|
| **Backend** | Laravel 11, PHP 8.3+ |
| **Frontend** | Vue.js, Vite |
| **Database** | MySQL/PostgreSQL |
| **Authentication** | Sanctum, Google OAuth |
| **AI Integration** | Google Gemini / Claude API |
| **Queue Jobs** | Notifications, Analysis |
| **File Storage** | Laravel Filesystem |
| **Caching** | Redis/File Cache |

---

## Key Business Rules

1. **Student Role**: Can only evaluate assigned faculty members
2. **Faculty Role**: Can view their own evaluations and reports
3. **Admin Role**: Full system access and management capabilities
4. **Evaluation Submission**: Must complete all required questions
5. **AI Analysis**: Triggered automatically after evaluation submission
6. **Report Generation**: Available after evaluation period closes
7. **Data Privacy**: Student anonymity maintained in faculty reports

---

## Security Features

- ✅ Role-Based Access Control (RBAC)
- ✅ Permission-Based Authorization
- ✅ API Token Authentication (Sanctum)
- ✅ CSRF Protection
- ✅ Data Encryption
- ✅ Audit Logging
- ✅ Rate Limiting

---

**Last Updated**: May 23, 2026
