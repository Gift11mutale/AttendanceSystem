```php
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "db.php";

/*
|--------------------------------------------------------------------------
| Lecturer Access Protection
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    die("Access Denied");
}

$success = '';
$error = '';

$course_name = '';
$course_code = '';

/*
|--------------------------------------------------------------------------
| CREATE COURSE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $course_name = trim($_POST['course_name'] ?? '');
    $course_code = trim($_POST['course_code'] ?? '');

    $lecturer_id = (int) $_SESSION['user_id'];

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($course_name === '' || $course_code === '') {

        $error = "Please fill in all required fields.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Check Course Code
        |--------------------------------------------------------------------------
        */

        $check = $conn->prepare(
            "SELECT id FROM courses WHERE course_code = ? LIMIT 1"
        );

        if (!$check) {

            $error = "Database error while checking the course.";

        } else {

            $check->bind_param("s", $course_code);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {

                $error = "This course code already exists.";

                $check->close();

            } else {

                $check->close();

                /*
                |--------------------------------------------------------------------------
                | Insert Course
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare(
                    "INSERT INTO courses
                    (course_name, course_code, lecturer_id)
                    VALUES (?, ?, ?)"
                );

                if (!$stmt) {

                    $error = "Unable to prepare the course creation.";

                } else {

                    $stmt->bind_param(
                        "ssi",
                        $course_name,
                        $course_code,
                        $lecturer_id
                    );

                    if ($stmt->execute()) {

                        /*
                        |--------------------------------------------------------------------------
                        | IMPORTANT:
                        | Get the ID of the newly created course.
                        |--------------------------------------------------------------------------
                        */

                        $new_course_id = $conn->insert_id;

                        /*
                        |--------------------------------------------------------------------------
                        | AUTOMATIC STUDY RESOURCE MANAGEMENT
                        |--------------------------------------------------------------------------
                        |
                        | The system checks the course name and automatically
                        | attaches appropriate learning resources.
                        |
                        */

                        $course_search =
                            strtolower(
                                $course_name . ' ' . $course_code
                            );

                        $resources = [];


                        /*
                        |--------------------------------------------------------------------------
                        | DATA STRUCTURES / ALGORITHMS
                        |--------------------------------------------------------------------------
                        */

                        if (
                            strpos($course_search, 'data structure') !== false ||
                            strpos($course_search, 'algorithm') !== false
                        ) {

                            $resources[] = [
                                'title' => 'GeeksforGeeks - Data Structures',
                                'description' =>
                                    'Learn arrays, linked lists, stacks, queues, trees, graphs and other data structures.',
                                'url' =>
                                    'https://www.geeksforgeeks.org/data-structures/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'Programiz - Data Structures',
                                'description' =>
                                    'Study data structures and algorithms with practical programming examples.',
                                'url' =>
                                    'https://www.programiz.com/dsa',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'GeeksforGeeks - Algorithms',
                                'description' =>
                                    'Practice and learn common algorithms and problem-solving techniques.',
                                'url' =>
                                    'https://www.geeksforgeeks.org/fundamentals-of-algorithms/',
                                'type' => 'Practice'
                            ];
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | JAVA
                        |--------------------------------------------------------------------------
                        */

                        elseif (
                            strpos($course_search, 'java') !== false
                        ) {

                            $resources[] = [
                                'title' => 'W3Schools - Java Tutorial',
                                'description' =>
                                    'Learn Java programming fundamentals, syntax, classes, objects and more.',
                                'url' =>
                                    'https://www.w3schools.com/java/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'Programiz - Java Programming',
                                'description' =>
                                    'Learn Java programming with examples and explanations.',
                                'url' =>
                                    'https://www.programiz.com/java-programming',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'W3Schools - Java Exercises',
                                'description' =>
                                    'Practice Java programming exercises online.',
                                'url' =>
                                    'https://www.w3schools.com/java/java_exercises.php',
                                'type' => 'Practice'
                            ];
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | DATABASE / SQL
                        |--------------------------------------------------------------------------
                        */

                        elseif (
                            strpos($course_search, 'database') !== false ||
                            strpos($course_search, 'dbms') !== false ||
                            strpos($course_search, 'sql') !== false
                        ) {

                            $resources[] = [
                                'title' => 'W3Schools - SQL Tutorial',
                                'description' =>
                                    'Learn SQL queries, tables, filtering, joins and database fundamentals.',
                                'url' =>
                                    'https://www.w3schools.com/sql/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'GeeksforGeeks - DBMS',
                                'description' =>
                                    'Learn database management systems, normalization, transactions and database concepts.',
                                'url' =>
                                    'https://www.geeksforgeeks.org/dbms/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'SQLBolt - SQL Practice',
                                'description' =>
                                    'Practice SQL queries interactively.',
                                'url' =>
                                    'https://sqlbolt.com/',
                                'type' => 'Practice'
                            ];
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | WEB DEVELOPMENT
                        |--------------------------------------------------------------------------
                        */

                        elseif (
                            strpos($course_search, 'web development') !== false ||
                            strpos($course_search, 'web programming') !== false ||
                            strpos($course_search, 'html') !== false ||
                            strpos($course_search, 'css') !== false
                        ) {

                            $resources[] = [
                                'title' => 'W3Schools - HTML',
                                'description' =>
                                    'Learn HTML structure, elements, forms, links and web page development.',
                                'url' =>
                                    'https://www.w3schools.com/html/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'W3Schools - CSS',
                                'description' =>
                                    'Learn CSS styling, layouts, responsive design and page formatting.',
                                'url' =>
                                    'https://www.w3schools.com/css/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'MDN Web Docs',
                                'description' =>
                                    'Comprehensive web development documentation for HTML, CSS and JavaScript.',
                                'url' =>
                                    'https://developer.mozilla.org/en-US/docs/Learn',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'W3Schools - HTML Exercises',
                                'description' =>
                                    'Practice HTML programming exercises.',
                                'url' =>
                                    'https://www.w3schools.com/html/html_exercises.asp',
                                'type' => 'Practice'
                            ];
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | JAVASCRIPT
                        |--------------------------------------------------------------------------
                        */

                        elseif (
                            strpos($course_search, 'javascript') !== false
                        ) {

                            $resources[] = [
                                'title' => 'W3Schools - JavaScript',
                                'description' =>
                                    'Learn JavaScript syntax, variables, functions, objects and events.',
                                'url' =>
                                    'https://www.w3schools.com/js/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'MDN - JavaScript Guide',
                                'description' =>
                                    'Detailed JavaScript learning resources and documentation.',
                                'url' =>
                                    'https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'W3Schools - JavaScript Exercises',
                                'description' =>
                                    'Practice JavaScript programming exercises.',
                                'url' =>
                                    'https://www.w3schools.com/js/js_exercises.asp',
                                'type' => 'Practice'
                            ];
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | PYTHON
                        |--------------------------------------------------------------------------
                        */

                        elseif (
                            strpos($course_search, 'python') !== false
                        ) {

                            $resources[] = [
                                'title' => 'W3Schools - Python',
                                'description' =>
                                    'Learn Python programming from the basics through practical examples.',
                                'url' =>
                                    'https://www.w3schools.com/python/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'Programiz - Python',
                                'description' =>
                                    'Learn Python programming concepts with examples.',
                                'url' =>
                                    'https://www.programiz.com/python-programming',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'W3Schools - Python Exercises',
                                'description' =>
                                    'Practice Python programming exercises.',
                                'url' =>
                                    'https://www.w3schools.com/python/python_exercises.asp',
                                'type' => 'Practice'
                            ];
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | CYBER SECURITY
                        |--------------------------------------------------------------------------
                        */

                        elseif (
                            strpos($course_search, 'cyber') !== false ||
                            strpos($course_search, 'security') !== false
                        ) {

                            $resources[] = [
                                'title' => 'OWASP',
                                'description' =>
                                    'Learn about web application security and common security risks.',
                                'url' =>
                                    'https://owasp.org/www-project-top-ten/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'Cisco Networking Academy',
                                'description' =>
                                    'Explore cybersecurity and networking learning materials.',
                                'url' =>
                                    'https://www.netacad.com/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'TryHackMe',
                                'description' =>
                                    'Interactive cybersecurity learning and practical exercises.',
                                'url' =>
                                    'https://tryhackme.com/',
                                'type' => 'Practice'
                            ];
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | NETWORKING
                        |--------------------------------------------------------------------------
                        */

                        elseif (
                            strpos($course_search, 'network') !== false
                        ) {

                            $resources[] = [
                                'title' => 'Cisco Networking Academy',
                                'description' =>
                                    'Learn computer networking, networking devices and protocols.',
                                'url' =>
                                    'https://www.netacad.com/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'GeeksforGeeks - Computer Networks',
                                'description' =>
                                    'Study networking concepts, protocols, models and network technologies.',
                                'url' =>
                                    'https://www.geeksforgeeks.org/computer-network-tutorials/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'GeeksforGeeks - Networking Practice',
                                'description' =>
                                    'Practice computer networking questions and concepts.',
                                'url' =>
                                    'https://www.geeksforgeeks.org/computer-network-tutorials/',
                                'type' => 'Practice'
                            ];
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | INFORMATION SYSTEMS
                        |--------------------------------------------------------------------------
                        */

                        elseif (
                            strpos($course_search, 'information system') !== false
                        ) {

                            $resources[] = [
                                'title' => 'GeeksforGeeks - Information Systems',
                                'description' =>
                                    'Explore information systems, databases, technology and organizational systems.',
                                'url' =>
                                    'https://www.geeksforgeeks.org/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'IBM - Information Technology',
                                'description' =>
                                    'Explore concepts related to information technology and information systems.',
                                'url' =>
                                    'https://www.ibm.com/think/topics/information-technology',
                                'type' => 'Tutorial'
                            ];
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | SOFTWARE ENGINEERING
                        |--------------------------------------------------------------------------
                        */

                        elseif (
                            strpos($course_search, 'software engineering') !== false
                        ) {

                            $resources[] = [
                                'title' => 'GeeksforGeeks - Software Engineering',
                                'description' =>
                                    'Learn software development life cycle, models, testing and software engineering concepts.',
                                'url' =>
                                    'https://www.geeksforgeeks.org/software-engineering/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'Atlassian - Agile',
                                'description' =>
                                    'Learn Agile development, Scrum and software project management concepts.',
                                'url' =>
                                    'https://www.atlassian.com/agile',
                                'type' => 'Tutorial'
                            ];
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | DEFAULT RESOURCES
                        |--------------------------------------------------------------------------
                        |
                        | If the system does not recognize the course name,
                        | provide general programming/computing resources.
                        |
                        */

                        else {

                            $resources[] = [
                                'title' => 'GeeksforGeeks',
                                'description' =>
                                    'Computer science tutorials, programming concepts and technical learning materials.',
                                'url' =>
                                    'https://www.geeksforgeeks.org/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'W3Schools',
                                'description' =>
                                    'Online tutorials and examples for programming and web technologies.',
                                'url' =>
                                    'https://www.w3schools.com/',
                                'type' => 'Tutorial'
                            ];

                            $resources[] = [
                                'title' => 'freeCodeCamp',
                                'description' =>
                                    'Free programming lessons, projects and coding practice.',
                                'url' =>
                                    'https://www.freecodecamp.org/',
                                'type' => 'Practice'
                            ];
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | INSERT AUTOMATIC RESOURCES
                        |--------------------------------------------------------------------------
                        */

                        if (!empty($resources)) {

                            $resource_stmt = $conn->prepare(
                                "INSERT INTO study_resources
                                (
                                    course_id,
                                    title,
                                    description,
                                    url,
                                    resource_type
                                )
                                VALUES (?, ?, ?, ?, ?)"
                            );

                            if ($resource_stmt) {

                                foreach ($resources as $resource) {

                                    $resource_stmt->bind_param(
                                        "issss",
                                        $new_course_id,
                                        $resource['title'],
                                        $resource['description'],
                                        $resource['url'],
                                        $resource['type']
                                    );

                                    $resource_stmt->execute();
                                }

                                $resource_stmt->close();
                            }
                        }


                        $success =
                            "Course created successfully and study resources were added automatically.";

                        $course_name = '';
                        $course_code = '';

                    } else {

                        $error =
                            "Unable to create the course. Please try again.";
                    }

                    $stmt->close();
                }
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>
<link rel="icon" type="image/png" href="assets/images/favicon.png">

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Create Course</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link
        rel="stylesheet"
        href="assets/dashboard.css?v=3">

</head>

<body>

<div class="wrapper">

    <?php include "includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "includes/navbar.php"; ?>

        <div class="container-fluid">

            <div class="dashboard-header mb-4">

                <h1>Create Course</h1>

                <p>
                    Create a new course and automatically add
                    recommended study resources.
                </p>

            </div>


            <div class="row justify-content-center">

                <div class="col-lg-8 col-xl-7">

                    <div class="card dashboard-card border-0 shadow-sm">

                        <div class="card-header bg-white">

                            <h5 class="mb-0">

                                <i
                                    class="bi bi-journal-plus me-2 text-success">
                                </i>

                                Course Details

                            </h5>

                        </div>


                        <div class="card-body">

                            <?php if ($success !== ''): ?>

                                <div
                                    class="alert alert-success"
                                    role="alert">

                                    <i
                                        class="bi bi-check-circle-fill me-2">
                                    </i>

                                    <?php
                                    echo htmlspecialchars($success);
                                    ?>

                                </div>

                            <?php endif; ?>


                            <?php if ($error !== ''): ?>

                                <div
                                    class="alert alert-danger"
                                    role="alert">

                                    <i
                                        class="bi bi-exclamation-triangle-fill me-2">
                                    </i>

                                    <?php
                                    echo htmlspecialchars($error);
                                    ?>

                                </div>

                            <?php endif; ?>


                            <form method="POST">

                                <div class="mb-4">

                                    <label
                                        for="course_name"
                                        class="form-label fw-semibold">

                                        Course Name

                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="course_name"
                                        name="course_name"
                                        value="<?php
                                        echo htmlspecialchars(
                                            $course_name
                                        );
                                        ?>"
                                        placeholder="e.g. Data Structures"
                                        required>

                                </div>


                                <div class="mb-4">

                                    <label
                                        for="course_code"
                                        class="form-label fw-semibold">

                                        Course Code

                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="course_code"
                                        name="course_code"
                                        value="<?php
                                        echo htmlspecialchars(
                                            $course_code
                                        );
                                        ?>"
                                        placeholder="e.g. ICT 450"
                                        required>

                                    <div class="form-text">

                                        Enter a unique course code.

                                        Study resources will be
                                        automatically assigned based
                                        on the course.

                                    </div>

                                </div>


                                <div class="d-flex gap-2">

                                    <button
                                        type="submit"
                                        class="btn btn-success">

                                        <i
                                            class="bi bi-plus-circle me-2">
                                        </i>

                                        Create Course

                                    </button>


                                    <a
                                        href="lecturer_dashboard.php"
                                        class="btn btn-outline-secondary">

                                        <i
                                            class="bi bi-arrow-left me-2">
                                        </i>

                                        Back to Dashboard

                                    </a>

                                </div>

                            </form>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>

</html>
```
