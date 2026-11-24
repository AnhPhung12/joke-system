# COMP1842 Option 2 — Project Structure

Use this order when presenting the work: required core functionality first, then enhancements.

```text
COMP1842_OPTION_2_SUBMISSION/
├── 01_CORE_SYSTEMS/
│   ├── 01_JOKE_SYSTEM/
│   │   └── JOKÉ/                         # Current workspace
│   │       ├── index.php                 # Home entry point
│   │       ├── login.php                 # Sign in
│   │       ├── register.php              # Sign up
│   │       ├── logout.php                # Sign out
│   │       ├── create-joke.php           # Create a joke
│   │       ├── edit-joke.php             # Edit own joke
│   │       ├── delete-joke.php           # Delete own joke
│   │       ├── my-jokes.php              # List current user's jokes
│   │       ├── joke.php                  # View a joke
│   │       ├── includes/
│   │       │   ├── DatabaseFunctions.php # OOP database function library
│   │       │   ├── User.php              # Account and author data model
│   │       │   ├── AuthorController.php  # Author listing controller
│   │       │   ├── AuthController.php    # Registration and login actions
│   │       │   ├── Joke.php              # Joke data model / CRUD
│   │       │   ├── JokeController.php   # Joke CRUD and ownership checks
│   │       │   ├── DatabaseCore.php      # PDO connection
│   │       │   ├── SessionCore.php       # Session handling
│   │       │   ├── RouterCore.php        # Request routing
│   │       │   ├── ViewCore.php          # View rendering
│   │       │   └── dispatch.php          # Shared route dispatcher
│   │       ├── templates/                # Basic pages and forms
│   │       ├── config/config.php         # Database and app configuration
│   │       └── database/joke_db_backup.sql
│   │
│   └── 02_OWN_SYSTEM/
│       └── <separate custom system folder and exported database>
│
├── 02_ENHANCEMENTS/
│   └── JOKÉ features (in the same JOKÉ source tree)
│       ├── admin/                        # Admin and moderation screens
│       ├── includes/AdminController.php  # User/category/joke management
│       ├── includes/ModerationController.php
│       ├── includes/PasswordResetController.php
│       ├── includes/ProfileController.php
│       ├── includes/CommunityController.php
│       ├── includes/GameController.php   # Joke games / playground
│       ├── includes/AIService.php        # AI-assisted game features
│       ├── includes/PasswordReset.php    # Password reset flow
│       ├── includes/Comment.php          # Comments
│       ├── includes/Moderation.php       # Reports and moderation data
│       ├── includes/Game.php             # Game sessions and messages
│       ├── assets/vendor/tinymce/        # Rich-text editor
│       └── database/joke_db_backup.sql   # Includes the extended schema/data
│
└── 03_WALKTHROUGH_AND_TESTING_REPORT/
    └── COMP1842_Option2_Report.pdf       # One report covering both systems
```

## Coursework status represented by this structure

- **Core JOKÉ files:** present in the current workspace.
- **Enhancement files:** present in the current workspace; they should be introduced after the core features in the walkthrough.
- **Second system:** must be a separate, working system for Option 2 Part B. It is not inside the current JOKÉ directory.
- **Submission report:** the coursework requires one PDF covering both systems, diagrams, annotated screenshots, and structured testing evidence.
- **ZIP deliverables:** submit one ZIP per system, each including its exported database.

This is a presentation map of the source tree. The second system and report need to be supplied separately; folder names alone do not demonstrate that those deliverables are complete.
