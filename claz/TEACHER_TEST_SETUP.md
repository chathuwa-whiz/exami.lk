# Teacher Test Paper Feature - Setup Instructions

This feature allows teachers to test their own papers before assigning them to students.

## What's New

1. **Test Paper Button** - Added a "Test" button in the manage_papers.php page next to each paper
2. **Test Paper Page** (test_paper.php) - Teachers can answer their own questions with a timer
3. **Test Result Page** (test_result.php) - Shows detailed results with correct/incorrect answers
4. **Database Tables** - Two new tables to store test attempts and responses

## Database Setup

You need to create two new tables to store teacher test attempts and responses. Choose one of the methods below:

### Method 1: Run PHP Script (Easiest)
1. Make sure XAMPP/MySQL is running
2. Navigate to the application root directory: `c:\xampp\htdocs\claz\`
3. Run the migration script:
   ```
   php add_teacher_test_tables.php
   ```

### Method 2: Run SQL Directly (phpMyAdmin or MySQL CLI)
1. Open phpMyAdmin or your MySQL client
2. Select the `admin_exami` database
3. Copy and paste the contents of `add_teacher_test_tables.sql` into the SQL query box
4. Click Execute

### Method 3: Manual SQL Execution
Open your MySQL CLI and run:
```sql
USE admin_exami;
SOURCE add_teacher_test_tables.sql;
```

## Tables Created

### teacher_test_attempts
- Stores each teacher's test attempt for a paper
- Fields: id, teacher_id, paper_id, started_at, submitted_at
- Relationships: References users and papers tables

### teacher_test_responses
- Stores the answers selected by teachers during test attempts
- Fields: id, test_attempt_id, question_id, selected_option_id
- Relationships: References teacher_test_attempts, questions, and answer_options tables

## Files Added

- `public/teacher/test_paper.php` - Main test paper page with timer and questions
- `public/teacher/test_result.php` - Shows test results with detailed analysis
- `add_teacher_test_tables.php` - PHP migration script
- `add_teacher_test_tables.sql` - SQL migration file

## Modified Files

- `public/teacher/manage_papers.php` - Added "Test" button to the papers list

## How It Works

1. Teacher visits "Manage Papers" page
2. Clicks the "Test" button on any paper
3. Answers all questions with a timer countdown
4. Can save progress (auto-saves every 30 seconds)
5. Submits to see detailed results
6. Results show:
   - Overall score and percentage
   - Question-by-question breakdown
   - Correct vs incorrect answers
   - All options for reference

## Features

✓ Timer countdown with auto-submit when time expires
✓ Auto-save progress every 30 seconds
✓ Manual save option
✓ Live math equation rendering (KaTeX)
✓ Image and PDF support in questions
✓ Detailed result analysis
✓ Performance feedback (Excellent/Good/Fair/Needs Work/Poor)
